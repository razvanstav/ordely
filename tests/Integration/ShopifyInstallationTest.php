<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Adapters\Shopify\{AppConfig,Installations,ShopDomain,IdTokenVerifier,WebhookInbox,ShopifyUnavailable,AuthorizationLost};
use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId};
use Ordely\Identity\Domain\{TenantContext,AccessDenied,Role};
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Infrastructure\Http\Problem;
use Ordely\Operations\Domain\Conflict;
use Ordely\Shared\Id;
use Ordely\Tests\Support\{DatabaseTestCase,FakeShopifyGateway,ShopifyFixtures as F};
use Symfony\Component\HttpFoundation\Request;

final class ShopifyInstallationTest extends DatabaseTestCase
{
    private Installations $service;
    private SecretCipher $cipher;
    private FakeShopifyGateway $gateway;
    private TenantContext $actor;
    private string $storeId;
    private string $code;
    protected function setUp(): void
    {
        parent::setUp();$this->gateway=new FakeShopifyGateway();$this->cipher=new SecretCipher(new KeyRing('test',['test'=>random_bytes(32)]));
        $this->service=new Installations($this->db,F::config(),$this->cipher,$this->gateway);$this->actor=$this->tenant();$this->storeId=$this->store($this->actor);
        $this->db->run("UPDATE stores SET platform_key='shopify' WHERE id=?",[Id::bytes($this->storeId)]);$this->code=$this->intent();
    }
    private function intent(): string {return $this->service->intent($this->actor,$this->storeId,new ShopDomain('ordely-test.myshopify.com'))['code'];}
    private function context(string $id): ConnectionContext {return new ConnectionContext(new MerchantId($this->actor->merchantId),new StoreId($this->storeId),new ConnectionId($id),CorrelationId::new());}
    private function state(string $id): string {return (string)$this->db->run('SELECT status FROM provider_connections WHERE id=?',[Id::bytes($id)])->fetchColumn();}
    private function installed(): string {return $this->service->connect(F::idToken(),$this->code);}
    private function webhook(string $delivery,string $topic='app/uninstalled',?string $time=null,string $shopId='123456'): Request
    {
        $body=json_encode($topic==='app/uninstalled'?['id'=>$shopId,'myshopify_domain'=>'ordely-test.myshopify.com']:['shop_id'=>$shopId,'shop_domain'=>'ordely-test.myshopify.com','customer'=>['email'=>'synthetic@example.test']],JSON_THROW_ON_ERROR);
        return Request::create('/webhooks/shopify','POST',server:['HTTP_X_SHOPIFY_HMAC_SHA256'=>base64_encode(hash_hmac('sha256',$body,F::config()->secret(),true)),'HTTP_X_SHOPIFY_SHOP_DOMAIN'=>'ordely-test.myshopify.com','HTTP_X_SHOPIFY_TOPIC'=>$topic,'HTTP_X_SHOPIFY_WEBHOOK_ID'=>$delivery,'HTTP_X_SHOPIFY_TRIGGERED_AT'=>$time??gmdate('Y-m-d\TH:i:s\Z',time()+1)],content:$body);
    }
    private function receive(Request $request): void {(new WebhookInbox($this->db,F::config(),$this->cipher,$this->service))->receive($request);}
    public function testConnectIsIdempotentEncryptedAndRequiresBothIdentities(): void
    {
        $id=$this->installed();self::assertSame($id,$this->installed());self::assertSame(1,$this->gateway->exchanges);
        $envelope=(string)$this->db->run('SELECT credentials_envelope FROM provider_connections WHERE id=?',[Id::bytes($id)])->fetchColumn();
        self::assertStringNotContainsString('SYNTHETIC',$envelope);self::assertSame('SYNTHETIC-ACCESS-0',$this->cipher->decrypt($this->actor->merchantId,$id,'shopify',$envelope)->reveal()['accessToken']);
        self::assertTrue($this->service->status((new IdTokenVerifier(F::config()))->verify(F::idToken()))['connected']);
        self::assertSame(hash('sha256',$this->code),bin2hex((string)$this->db->run('SELECT code_hash FROM shopify_link_intents WHERE merchant_id=?',[Id::bytes($this->actor->merchantId)])->fetchColumn()));
    }
    public function testRemovedMembershipCannotConsumePreviouslyIssuedCode(): void
    {
        $this->db->run("UPDATE memberships SET status='disabled' WHERE id=?",[Id::bytes($this->actor->membershipId)]);
        try{$this->installed();self::fail();}catch(AccessDenied){self::assertSame(0,$this->gateway->exchanges);}
    }
    public function testExpiredCodeAndWrongRolesFailBeforeNetwork(): void
    {
        $this->db->run('UPDATE shopify_link_intents SET expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 MINUTE) WHERE code_hash=?',[hash('sha256',$this->code,true)]);
        try{$this->installed();self::fail();}catch(AccessDenied){self::assertSame(0,$this->gateway->exchanges);}
        foreach([[Role::Operator,true],[Role::Admin,false]] as [$role,$all]){$actor=$this->tenant($role,$all);try{$this->service->intent($actor,$this->storeId,new ShopDomain('ordely-test.myshopify.com'));self::fail();}catch(AccessDenied $error){self::assertNotSame("",$error->getMessage());}}
    }
    public function testForeignTenantCannotTakeOverShopEvenAfterRevocation(): void
    {
        $id=$this->installed();$this->service->revoke($this->actor->merchantId,$this->storeId,$id);$other=$this->tenant();$store=$this->store($other);$this->db->run("UPDATE stores SET platform_key='shopify' WHERE id=?",[Id::bytes($store)]);
        $code=$this->service->intent($other,$store,new ShopDomain('ordely-test.myshopify.com'))['code'];
        try{$this->service->connect(F::idToken(),$code);self::fail();}catch(AccessDenied){self::assertSame(1,$this->gateway->exchanges);}
    }
    public function testRefreshPersistsEvenWhenFollowingProbeTimesOut(): void
    {
        $this->gateway->expired=true;$id=$this->installed();$this->gateway->failure=new ShopifyUnavailable();
        try{$this->service->check($this->context($id));self::fail();}catch(ShopifyUnavailable $error){self::assertNotSame("",$error->getMessage());}
        $this->gateway->failure=null;$this->service->check($this->context($id));self::assertSame(1,$this->gateway->refreshes);self::assertSame('active',$this->state($id));
    }
    public function testRemoteRevocationCommitsAndFutureWorkIsBlocked(): void
    {
        $id=$this->installed();$this->gateway->failure=new AuthorizationLost();
        try{$this->service->check($this->context($id));self::fail();}catch(AuthorizationLost){self::assertSame('revoked',$this->state($id));}
        $calls=$this->gateway->probes;try{$this->service->check($this->context($id));self::fail();}catch(AccessDenied){self::assertSame($calls,$this->gateway->probes);}
    }
    public function testUninstallIsDurableDeduplicatedAndBlocksConnection(): void
    {
        $id=$this->installed();$request=$this->webhook('delivery-uninstall');$this->receive($request);$this->receive($request);
        self::assertSame('revoked',$this->state($id));self::assertSame(1,(int)$this->db->run('SELECT COUNT(*) FROM shopify_webhook_events WHERE merchant_id=?',[Id::bytes($this->actor->merchantId)])->fetchColumn());
        self::assertSame('processed',$this->db->run('SELECT status FROM shopify_webhook_events WHERE merchant_id=?',[Id::bytes($this->actor->merchantId)])->fetchColumn());
    }
    public function testLateUninstallCannotRevokeReinstallation(): void
    {
        $old=$this->installed();$this->code=$this->intent();$new=$this->installed();self::assertNotSame($old,$new);self::assertSame('revoked',$this->state($old));
        $this->receive($this->webhook('delivery-late',time:gmdate('Y-m-d\TH:i:s\Z',time()-10)));self::assertSame('active',$this->state($new));
        $this->receive($this->webhook('delivery-no-time',time:''));self::assertSame('active',$this->state($new));
    }
    public function testBadSignatureAndCrossShopPayloadCauseNoChanges(): void
    {
        $id=$this->installed();$bad=$this->webhook('delivery-bad');$bad->headers->set('X-Shopify-Hmac-SHA256','invalid');
        foreach([$bad,$this->webhook('delivery-foreign',shopId:'654321')] as $request){try{$this->receive($request);self::fail();}catch(Problem $error){self::assertContains($error->status,[400,401]);}}
        self::assertSame('active',$this->state($id));self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM shopify_webhook_events WHERE merchant_id=?',[Id::bytes($this->actor->merchantId)])->fetchColumn());
    }
    public function testPrivacyReceiptIsEncryptedAndRemainsPendingExplicitProcessing(): void
    {
        $id=$this->installed();$this->receive($this->webhook('delivery-privacy','customers/data_request'));
        $row=$this->db->one('SELECT * FROM shopify_webhook_events WHERE merchant_id=?',[Id::bytes($this->actor->merchantId)]);self::assertNotNull($row);self::assertSame('needs_review',$row['status']);self::assertStringNotContainsString('synthetic@example.test',(string)$row['payload_envelope']);
        self::assertStringContainsString('synthetic@example.test',(string)base64_decode($this->cipher->decrypt($this->actor->merchantId,bin2hex((string)$row['id']),'shopify-webhook',(string)$row['payload_envelope'])->reveal()['body0']));self::assertSame('active',$this->state($id));
        try{$this->receive($this->webhook('delivery-privacy','customers/redact'));self::fail();}catch(Conflict $error){self::assertSame("webhook_conflict",$error->getMessage());}
    }
    public function testWorkerCannotUseConnectionFromAnotherStoreAndGenericBindingCannotMoveIt(): void
    {
        $id=$this->installed();$otherStore=$this->store($this->actor);
        $context=new ConnectionContext(new MerchantId($this->actor->merchantId),new StoreId($otherStore),new ConnectionId($id),CorrelationId::new());
        try{$this->service->check($context);self::fail('Cross-store work accepted.');}catch(AccessDenied $error){self::assertSame('connection_unavailable',$error->getMessage());}
        $connections=new \Ordely\Integrations\Infrastructure\Connections($this->db,\Ordely\Tests\Support\IntegrationFixtures::registry(),$this->cipher);
        $this->expectException(\InvalidArgumentException::class);$connections->bind($this->actor,$id,1,$otherStore,true);
    }
    public function testUninstallStillRevokesWhenMerchantIsDisabled(): void
    {
        $id=$this->installed();$this->db->run("UPDATE merchants SET status='disabled' WHERE id=?",[Id::bytes($this->actor->merchantId)]);
        $this->receive($this->webhook('delivery-disabled'));self::assertSame('revoked',$this->state($id));
    }
}
