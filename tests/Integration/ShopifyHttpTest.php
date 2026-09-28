<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Identity\Infrastructure\Sessions;
use Ordely\Infrastructure\Http\Application;
use Ordely\Shared\Id;
use Ordely\Tests\Support\{DatabaseTestCase,FakeShopifyGateway,ShopifyFixtures as F};
use Symfony\Component\HttpFoundation\{Request,Response};

final class ShopifyHttpTest extends DatabaseTestCase
{
    /** @var array<string,mixed> */ private array $previous=[];
    private string $keyFile;
    private FakeShopifyGateway $gateway;
    protected function setUp(): void
    {
        parent::setUp();$this->gateway=new FakeShopifyGateway();$this->keyFile=dirname(__DIR__,2).'/var/shopify-http-key-'.Id::new().'.json';
        file_put_contents($this->keyFile,json_encode(['active'=>'test','keys'=>['test'=>base64_encode(random_bytes(32))]],JSON_THROW_ON_ERROR));
        foreach(['ORDELY_KEYRING_FILE'=>$this->keyFile,'ORDELY_SHOPIFY_CLIENT_ID'=>F::config()->clientId,'ORDELY_SHOPIFY_CLIENT_SECRET'=>F::config()->secret(),'ORDELY_SHOPIFY_DEV_STORE'=>'ordely-test.myshopify.com'] as $name=>$value){$this->previous[$name]=$_ENV[$name]??null;$_ENV[$name]=$value;}
    }
    protected function tearDown(): void
    {
        foreach($this->previous as $name=>$value){if($value===null){unset($_ENV[$name]);}else{$_ENV[$name]=$value;}}
        unlink($this->keyFile);parent::tearDown();
    }
    /** @param array<string,mixed> $body */
    private function request(string $path,string $method='GET',array $body=[],string $session='',string $idToken='',bool $csrf=true): Response
    {
        $server=['CONTENT_TYPE'=>'application/json','HTTP_ORIGIN'=>'https://localhost','HTTP_AUTHORIZATION'=>'Bearer '.$idToken];if($csrf){$server['HTTP_X_CSRF_TOKEN']=Sessions::csrf($session);}
        return (new Application(fn():\PDO=>$this->db->pdo,$this->gateway))->handle(Request::create('https://localhost'.$path,$method,[],['ordely_session'=>$session],[],$server,json_encode((object)$body,JSON_THROW_ON_ERROR)));
    }
    public function testEmbeddedConnectUsesIdTokenAndOwnerCodeWithoutThirdPartyCookie(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$this->db->run("UPDATE stores SET platform_key='shopify' WHERE id=?",[Id::bytes($store)]);
        $session=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');$body=['storeId'=>$store,'shop'=>'ordely-test.myshopify.com'];
        self::assertSame(403,$this->request('/api/shopify/intents','POST',$body,$session,csrf:false)->getStatusCode());
        $intent=$this->request('/api/shopify/intents','POST',$body,$session);self::assertSame(201,$intent->getStatusCode());
        $data=json_decode((string)$intent->getContent(),true,flags:JSON_THROW_ON_ERROR);
        self::assertSame(401,$this->request('/shopify/connect','POST',['code'=>$data['code']])->getStatusCode());
        $connected=$this->request('/shopify/connect','POST',['code'=>$data['code']],idToken:F::idToken());self::assertSame(200,$connected->getStatusCode());
        self::assertStringNotContainsString('SYNTHETIC',(string)$connected->getContent());self::assertSame([],$connected->headers->getCookies());
        $status=$this->request('/shopify/status',idToken:F::idToken());self::assertStringContainsString('"connected":true',(string)$status->getContent());
        self::assertSame(401,$this->request('/api/shopify/events')->getStatusCode());
    }
    public function testEmbeddedCspIsLimitedToShopifyAndAllowedShop(): void
    {
        $home=$this->request('/?shop=ordely-test.myshopify.com');self::assertSame(200,$home->getStatusCode());
        self::assertStringContainsString('frame-ancestors https://admin.shopify.com https://ordely-test.myshopify.com',(string)$home->headers->get('Content-Security-Policy'));
        self::assertStringNotContainsString(F::config()->secret(),(string)$home->getContent());
        self::assertStringContainsString("frame-ancestors 'none'",(string)$this->request('/')->headers->get('Content-Security-Policy'));
        self::assertSame(400,$this->request('/shopify?shop=attacker.example')->getStatusCode());
        self::assertSame(400,$this->request('/shopify?shop=foreign.myshopify.com')->getStatusCode());
    }
    public function testInvalidWebhookIsRejectedBeforeAnyTenantLookup(): void
    {
        self::assertSame(401,$this->request('/webhooks/shopify','POST',['id'=>123456])->getStatusCode());
        $invalid=$this->request('/shopify/status',idToken:'invalid');self::assertSame(401,$invalid->getStatusCode());self::assertSame('1',$invalid->headers->get('X-Shopify-Retry-Invalid-Session-Request'));self::assertSame(0,$this->gateway->exchanges);
    }
}
