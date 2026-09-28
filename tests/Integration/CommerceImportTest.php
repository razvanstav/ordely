<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;

use Ordely\Adapters\Shopify\{Installations,ShopDomain,GraphqlReader};
use Ordely\Commerce\Application\ImportService;
use Ordely\Commerce\Infrastructure\{ImportRepository,OrderCipher,CommerceQuery};
use Ordely\Core\Contracts\{ConnectionContext,ErrorCategory,ProviderFailure};
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId};
use Ordely\Identity\Domain\{AccessDenied,TenantContext,Role};
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Operations\Application\Worker;
use Ordely\Operations\Infrastructure\MySqlJobQueue;
use Ordely\Shared\Id;
use Ordely\Tests\Support\{DatabaseTestCase,FakeShopifyGateway,ImportFixtures,ShopifyFixtures as F};

final class CommerceImportTest extends DatabaseTestCase
{
    private TenantContext $actor;
    private ConnectionContext $context;
    private Installations $installations;
    private FakeShopifyGateway $gateway;
    private ImportFixtures $source;
    private ImportService $service;
    private OrderCipher $cipher;
    private SecretCipher $secret;
    protected function setUp(): void
    {
        parent::setUp();$this->actor=$this->tenant();$store=$this->store($this->actor);
        $this->db->run("UPDATE stores SET platform_key='shopify' WHERE id=?",[Id::bytes($store)]);
        $secret=new SecretCipher(new KeyRing('test',['test'=>random_bytes(32)]));$this->secret=$secret;$this->cipher=new OrderCipher($secret);$this->gateway=new FakeShopifyGateway();
        $this->installations=new Installations($this->db,F::config(),$secret,$this->gateway);
        $intent=$this->installations->intent($this->actor,$store,new ShopDomain('ordely-test.myshopify.com'));
        $connection=$this->installations->connect(F::idToken(),$intent['code']);
        $this->context=new ConnectionContext(new MerchantId($this->actor->merchantId),new StoreId($store),new ConnectionId($connection),CorrelationId::new());
        $this->source=new ImportFixtures();$this->service=new ImportService($this->db,$this->source,new ImportRepository($this->db,$this->cipher));
    }
    private function rowCount(string $table): int { return (int)$this->db->run('SELECT COUNT(*) FROM '.$table.' WHERE merchant_id=?',[Id::bytes($this->actor->merchantId)])->fetchColumn(); }
    private function drain(): void
    {
        $worker=new Worker(new MySqlJobQueue($this->db),[$this->service]);
        for($i=0;$i<30;++$i){$result=$worker->once($this->actor->merchantId);if($result==='idle'){return;}self::assertSame('succeeded',$result);}
        self::fail('Queue failed to drain.');
    }
    public function testPagedRunPublishesAtomicallyAndRepeatDoesNotDuplicateOrdersOrEvents(): void
    {
        $run=$this->service->start($this->actor,$this->context);self::assertSame($run,$this->service->start($this->actor,$this->context));
        $queue=new MySqlJobQueue($this->db);$job=$queue->claim($this->actor->merchantId);self::assertNotNull($job);$this->service->handle($job);$queue->acknowledge($job);
        self::assertSame(0,$this->rowCount('commerce_records'));
        $this->drain();self::assertSame(4,$this->rowCount('commerce_records'));self::assertSame(0,$this->rowCount('commerce_sync_records'));self::assertSame(1,$this->rowCount('outbox_events'));
        $order=$this->db->one("SELECT * FROM commerce_records WHERE merchant_id=? AND kind='order'",[Id::bytes($this->actor->merchantId)]);self::assertNotNull($order);
        self::assertStringNotContainsString('synthetic@example.test',(string)$order['document']);
        $decoded=$this->cipher->open($this->actor->merchantId,$this->context->store->value,bin2hex((string)$order['id']),(string)$order['document']);self::assertCount(2,$decoded['lines']);
        $this->service->start($this->actor,$this->context);$this->drain();self::assertSame(4,$this->rowCount('commerce_records'));self::assertSame(1,$this->rowCount('outbox_events'));
        $query=new CommerceQuery($this->db,$this->cipher);self::assertSame('Test product',$query->list($this->actor,$this->context->store->value,'variant')['records'][0]['parentTitle']);
        self::assertSame('Test product · M',$query->list($this->actor,$this->context->store->value,'inventory')['records'][0]['parentTitle']);
        self::assertSame(1,(int)$this->db->run('SELECT version FROM commerce_records WHERE id=?',[(string)$order['id']])->fetchColumn());
    }
    public function testReconciliationUpdatesFinancialFactsAndRemovesMissingCatalogOnlyAfterCompletion(): void
    {
        $this->service->start($this->actor,$this->context);$this->drain();$this->source->omitCatalog=true;$this->source->total=12345;$this->source->date='2026-09-28T11:00:00.000000Z';
        $this->service->start($this->actor,$this->context);$this->drain();
        self::assertSame(1,(int)$this->db->run('SELECT COUNT(*) FROM commerce_records WHERE merchant_id=? AND active=TRUE',[Id::bytes($this->actor->merchantId)])->fetchColumn());
        self::assertSame(2,$this->rowCount('outbox_events'));
    }
    public function testExpiredLeaseAfterRemoteReadCannotPublishOrAdvanceTask(): void
    {
        $this->service->start($this->actor,$this->context);$queue=new MySqlJobQueue($this->db);$job=$queue->claim($this->actor->merchantId);self::assertNotNull($job);
        $this->source->beforePage=function()use($job):void{$this->db->run('UPDATE jobs SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[Id::bytes($job->id)]);};
        try{$this->service->handle($job);self::fail();}catch(\Ordely\Operations\Domain\LeaseLost){self::assertSame(0,$this->rowCount('commerce_sync_records'));}
        $this->source->beforePage=null;$this->drain();self::assertSame(4,$this->rowCount('commerce_records'));
    }
    public function testCrossTenantAndCrossStoreAccessAreDeniedAtStartAndQuery(): void
    {
        $other=$this->tenant();
        try{$this->service->start($other,$this->context);self::fail();}catch(AccessDenied){self::assertSame(0,$this->source->calls);}
        $bad=new ConnectionContext($this->context->merchant,new StoreId($this->store($this->actor)),$this->context->connection,CorrelationId::new());
        try{$this->service->start($this->actor,$bad);self::fail();}catch(AccessDenied){self::assertSame(0,$this->rowCount('commerce_sync_runs'));}
        $this->expectException(AccessDenied::class);(new CommerceQuery($this->db,$this->cipher))->list($other,$this->context->store->value,'order');
    }
    public function testConnectionRevokedDuringReadCannotCommit(): void
    {
        $this->service->start($this->actor,$this->context);
        $this->source->beforePage=function():void{$this->db->run("UPDATE provider_connections SET status='revoked' WHERE id=?",[Id::bytes($this->context->connection->value)]);};
        $worker=new Worker(new MySqlJobQueue($this->db),[$this->service]);self::assertSame('dead',$worker->once($this->actor->merchantId));self::assertSame(0,$this->rowCount('commerce_records'));
    }
    public function testRetryLeavesWatermarkAndRecordsUnchangedUntilSuccess(): void
    {
        $this->service->start($this->actor,$this->context);$this->source->failure=new ProviderFailure(ErrorCategory::Transient,30);
        $worker=new Worker(new MySqlJobQueue($this->db),[$this->service]);self::assertSame('retry',$worker->once($this->actor->merchantId));
        self::assertNull($this->db->run('SELECT watermark FROM commerce_sync_heads WHERE merchant_id=?',[Id::bytes($this->actor->merchantId)])->fetchColumn());self::assertSame(0,$this->rowCount('commerce_records'));
        $this->source->failure=null;$this->db->run('UPDATE jobs SET available_at=UTC_TIMESTAMP(6) WHERE merchant_id=?',[Id::bytes($this->actor->merchantId)]);$this->drain();self::assertSame(4,$this->rowCount('commerce_records'));
    }
    public function testRestartInvalidatesAlreadyClaimedOldWork(): void
    {
        $old=$this->service->start($this->actor,$this->context);$queue=new MySqlJobQueue($this->db);$job=$queue->claim($this->actor->merchantId);self::assertNotNull($job);
        $new=$this->service->start($this->actor,$this->context,true,true);self::assertNotSame($old,$new);$this->service->handle($job);$queue->acknowledge($job);self::assertSame(0,$this->source->calls);
        $this->drain();self::assertSame(4,$this->rowCount('commerce_records'));
    }
    public function testGraphqlThrottlingAndPartialErrorsAreNeverImported(): void
    {
        foreach([['status'=>429,'body'=>'','retryAfter'=>9],['status'=>200,'body'=>'{"data":{"orders":{}},"errors":[{"extensions":{"code":"THROTTLED"}}]}']] as $response){
            $reader=new GraphqlReader($this->installations,F::config(),static fn():array=>$response);
            try{$reader->read($this->context,'orders',[]);self::fail();}catch(ProviderFailure $error){self::assertSame(ErrorCategory::Transient,$error->category);self::assertGreaterThan(0,$error->retryAfterSeconds);}
        }
        $reader=new GraphqlReader($this->installations,F::config(),static fn():array=>['status'=>200,'body'=>'{"data":{"orders":{}},"errors":[{"extensions":{"code":"ACCESS_DENIED"}}]}']);
        try{$reader->read($this->context,'orders',[]);self::fail();}catch(ProviderFailure $error){self::assertSame(ErrorCategory::Authentication,$error->category);}
    }
    public function testViewerCannotDecryptOrderDetails(): void
    {
        $this->service->start($this->actor,$this->context);$this->drain();$id=(string)$this->db->run("SELECT LOWER(HEX(id)) FROM commerce_records WHERE merchant_id=? AND kind='order'",[Id::bytes($this->actor->merchantId)])->fetchColumn();
        $this->db->run("UPDATE memberships SET role='viewer' WHERE id=?",[Id::bytes($this->actor->membershipId)]);
        $this->expectException(AccessDenied::class);(new CommerceQuery($this->db,$this->cipher))->order($this->actor,$this->context->store->value,$id);
    }
    private function privacyEvent(string $topic): string
    {
        $raw=json_encode(['shop_id'=>123456,'shop_domain'=>'ordely-test.myshopify.com','customer'=>['id'=>1],'orders_requested'=>[1],'orders_to_redact'=>[1]],JSON_THROW_ON_ERROR);
        $request=\Symfony\Component\HttpFoundation\Request::create('/webhooks/shopify','POST',server:['HTTP_X_SHOPIFY_HMAC_SHA256'=>base64_encode(hash_hmac('sha256',$raw,F::config()->secret(),true)),
            'HTTP_X_SHOPIFY_SHOP_DOMAIN'=>'ordely-test.myshopify.com','HTTP_X_SHOPIFY_TOPIC'=>$topic,'HTTP_X_SHOPIFY_WEBHOOK_ID'=>Id::new()],content:$raw);
        (new \Ordely\Adapters\Shopify\WebhookInbox($this->db,F::config(),$this->secret,$this->installations))->receive($request);
        return (string)$this->db->run('SELECT LOWER(HEX(id)) FROM shopify_webhook_events WHERE merchant_id=? AND topic=?',[Id::bytes($this->actor->merchantId),$topic])->fetchColumn();
    }
    public function testPrivacyExportIsScopedAndNotFalselyMarkedDelivered(): void
    {
        $this->service->start($this->actor,$this->context);$this->drain();$id=$this->privacyEvent('customers/data_request');
        $service=new \Ordely\Adapters\Shopify\PrivacyRequests($this->db,$this->secret);
        try{$service->process($this->tenant(),$id,'export');self::fail();}catch(AccessDenied){self::assertSame(4,$this->rowCount('commerce_records'));}
        $result=$service->process($this->actor,$id,'export');self::assertSame('prepared_not_delivered',$result['status']);self::assertCount(1,$result['orders']);
        self::assertSame('needs_review',$this->db->run('SELECT status FROM shopify_webhook_events WHERE id=?',[Id::bytes($id)])->fetchColumn());
        self::assertSame(['status'=>'processed'],$service->process($this->actor,$id,'confirm-delivered'));
    }
    public function testPrivacyErasurePreventsReimport(): void
    {
        $this->service->start($this->actor,$this->context);$this->drain();$id=$this->privacyEvent('customers/redact');
        (new \Ordely\Adapters\Shopify\PrivacyRequests($this->db,$this->secret))->process($this->actor,$id,'redact');self::assertSame(3,$this->rowCount('commerce_records'));
        $this->service->start($this->actor,$this->context,true);$this->drain();self::assertSame(3,$this->rowCount('commerce_records'));self::assertSame(0,$this->rowCount('commerce_sync_records'));
        self::assertSame('{}',base64_decode($this->secret->decrypt($this->actor->merchantId,$id,'shopify-webhook',(string)$this->db->run('SELECT payload_envelope FROM shopify_webhook_events WHERE id=?',[Id::bytes($id)])->fetchColumn())->reveal()['body0']));
    }
    public function testLateShopRedactCannotDeleteActiveReinstall(): void
    {
        $this->service->start($this->actor,$this->context);$this->drain();$id=$this->privacyEvent('shop/redact');
        try{(new \Ordely\Adapters\Shopify\PrivacyRequests($this->db,$this->secret))->process($this->actor,$id,'redact');self::fail();}
        catch(\Ordely\Operations\Domain\Conflict $error){self::assertSame('shop_reinstalled',$error->getMessage());self::assertSame(4,$this->rowCount('commerce_records'));}
    }
    public function testOrderChangedBetweenPagesFailsWithoutPublishingMixedLines(): void
    {
        $this->service->start($this->actor,$this->context);$worker=new Worker(new MySqlJobQueue($this->db),[$this->service]);
        $this->source->beforePage=function():void {
            if($this->db->one("SELECT 1 FROM commerce_sync_records WHERE merchant_id=? AND kind='order'",[Id::bytes($this->actor->merchantId)])!==null){$this->source->date='2026-09-28T12:00:00.000000Z';}
        };
        $failed=false;for($i=0;$i<20;++$i){$result=$worker->once($this->actor->merchantId);if($result==='dead'){$failed=true;break;}if($result==='idle'){break;}}
        self::assertTrue($failed);self::assertSame(0,$this->rowCount('commerce_records'));
    }
    public function testAbandonedStagingExpiresWithoutDeletingPublishedData(): void
    {
        $this->service->start($this->actor,$this->context);$this->drain();$run=$this->service->start($this->actor,$this->context);
        $this->db->run('UPDATE commerce_sync_runs SET started_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 8 DAY) WHERE id=?',[Id::bytes($run)]);
        self::assertSame(1,(new \Ordely\Commerce\Infrastructure\ImportRetention($this->db))->clean());
        self::assertSame(4,$this->rowCount('commerce_records'));self::assertSame('cancelled',$this->db->run('SELECT status FROM commerce_sync_runs WHERE id=?',[Id::bytes($run)])->fetchColumn());
    }
}
