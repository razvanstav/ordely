<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Adapters\Oblio\OblioProvider;
use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId};
use Ordely\Identity\Domain\{AccessDenied,Role,TenantContext};
use Ordely\Identity\Infrastructure\Sessions;
use Ordely\Infrastructure\Http\Application;
use Ordely\Integrations\Application\{ProviderDefinition,ProviderRegistry};
use Ordely\Integrations\Domain\{ProviderKind,Secrets};
use Ordely\Integrations\Infrastructure\{Connections,KeyRing,SecretCipher};
use Ordely\Invoicing\Application\ReadInvoiceConfiguration;
use Ordely\Shared\Id;
use Ordely\Tests\Support\{DatabaseTestCase,OblioFixtures as F};
use Symfony\Component\HttpFoundation\{Request,Response};

final class InvoiceConfigurationTest extends DatabaseTestCase
{
    private string $keyFile;
    private mixed $previousKeyFile;
    private ProviderRegistry $registry;
    private SecretCipher $cipher;
    private int $calls=0;
    private ?\Closure $duringCall=null;
    private int $upstreamStatus=200;
    protected function setUp(): void
    {
        parent::setUp();$this->keyFile=dirname(__DIR__,2).'/var/oblio-test-key-'.Id::new().'.json';
        file_put_contents($this->keyFile,json_encode(['active'=>'test','keys'=>['test'=>base64_encode(random_bytes(32))]],JSON_THROW_ON_ERROR));
        $this->previousKeyFile=$_ENV['ORDELY_KEYRING_FILE']??null;$_ENV['ORDELY_KEYRING_FILE']=$this->keyFile;
        $this->cipher=new SecretCipher(KeyRing::fromEnvironment());
        $this->registry=new ProviderRegistry(new ProviderDefinition('oblio','Oblio',ProviderKind::Invoice,['clientId','clientSecret'],fn(Secrets $secrets):OblioProvider=>new OblioProvider($secrets,function(string $method,string $path):array {
            ++$this->calls;if($this->duringCall!==null){$callback=$this->duringCall;$this->duringCall=null;$callback();}
            return $this->upstreamStatus===200?F::reply($path):['status'=>$this->upstreamStatus,'body'=>'PRIVATE upstream detail','retryAfter'=>12];
        }),OblioProvider::validateCredentials(...)));
    }
    protected function tearDown(): void
    {
        if($this->previousKeyFile===null){unset($_ENV['ORDELY_KEYRING_FILE']);}else{$_ENV['ORDELY_KEYRING_FILE']=$this->previousKeyFile;}
        if(is_file($this->keyFile)){unlink($this->keyFile);}parent::tearDown();
    }
    private function service(): Connections { return new Connections($this->db,$this->registry,$this->cipher); }
    private function connect(TenantContext $actor,string $store): string
    {
        $id=$this->service()->create($actor,Id::new(),'oblio','Synthetic Oblio',F::credentials());$this->service()->bind($actor,$id,1,$store);return $id;
    }
    private function context(TenantContext $actor,string $store,string $connection): ConnectionContext { return new ConnectionContext(new MerchantId($actor->merchantId),new StoreId($store),new ConnectionId($connection),CorrelationId::new()); }
    private function read(TenantContext $actor,ConnectionContext $context): void { (new ReadInvoiceConfiguration($this->db,$this->registry,$this->cipher))->read($actor,$context,2); }
    /** @param array<string,mixed> $body */
    private function request(string $token,array $body,bool $csrf=true,?string $origin=null): Response
    {
        $server=['CONTENT_TYPE'=>'application/json'];if($csrf){$server['HTTP_X_CSRF_TOKEN']=Sessions::csrf($token);}if($origin!==null){$server['HTTP_ORIGIN']=$origin;}
        return (new Application(fn():\PDO=>$this->db->pdo,invoiceRegistry:$this->registry))->handle(Request::create('https://localhost/api/invoice-configuration','POST',[],['ordely_session'=>$token],[],$server,json_encode((object)$body,JSON_THROW_ON_ERROR)));
    }
    public function testEncryptedConnectionAndHttpCatalogueReturnNoCredentialsAndAuditOnlyCounts(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=$this->connect($actor,$store);$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');
        $response=$this->request($token,['storeId'=>$store,'connectionId'=>$id,'version'=>2,'companyId'=>'TEST001']);
        self::assertSame(200,$response->getStatusCode(),(string)$response->getContent());self::assertStringContainsString('"percent":"7.1250"',(string)$response->getContent());self::assertStringContainsString('no-store',(string)$response->headers->get('Cache-Control'));self::assertSame(4,$this->calls);
        $stored=(string)$this->db->run('SELECT credentials_envelope FROM provider_connections WHERE id=?',[Id::bytes($id)])->fetchColumn();self::assertSame(F::credentials()->reveal(),$this->cipher->decrypt($actor->merchantId,$id,'oblio',$stored)->reveal());
        $audit=$this->db->run('SELECT action,safe_data FROM audit_logs WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchAll(\PDO::FETCH_ASSOC);
        $public=serialize([$stored,$response->getContent(),$this->service()->list($actor),$audit]);foreach(['SYNTHETIC-oblio-secret','oblio@example.test','synthetic-access-token'] as $secret){self::assertStringNotContainsString($secret,$public);}
        self::assertCount(1,array_filter($audit,static fn(array $entry):bool=>$entry['action']==='invoice_configuration_read'));self::assertStringNotContainsString('TEST001',serialize($audit));
    }
    public function testCsrfOriginMissingVersionAndForeignTenantStopBeforeNetwork(): void
    {
        $actor=$this->tenant();$foreign=$this->tenant();$store=$this->store($actor);$id=$this->connect($actor,$store);$sessions=new Sessions($this->db);
        $token=$sessions->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');$other=$sessions->login($foreign->userId.'@example.test',self::PASSWORD,'127.0.0.1');$body=['storeId'=>$store,'connectionId'=>$id,'version'=>2];
        self::assertSame(401,$this->request('',$body)->getStatusCode());self::assertSame(403,$this->request($token,$body,false)->getStatusCode());self::assertSame(403,$this->request($token,$body,true,'https://foreign.test')->getStatusCode());self::assertSame(403,$this->request($other,$body)->getStatusCode());self::assertSame(400,$this->request($token,['storeId'=>$store,'connectionId'=>$id])->getStatusCode());self::assertSame(0,$this->calls);
    }
    public function testRestrictedRolesAndStoresCannotReadSharedAccount(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=$this->connect($actor,$store);$context=$this->context($actor,$store,$id);
        foreach([Role::Finance,Role::Operator,Role::Viewer] as $role){$this->db->run('UPDATE memberships SET role=? WHERE id=?',[$role->value,Id::bytes($actor->membershipId)]);try{$this->read($actor,$context);self::fail('Expected current role check.');}catch(AccessDenied){self::addToAssertionCount(1);}}
        $this->db->run("UPDATE memberships SET role='admin',all_stores=0 WHERE id=?",[Id::bytes($actor->membershipId)]);try{$this->read($actor,$context);self::fail('Expected shared account restriction.');}catch(AccessDenied){self::addToAssertionCount(1);}
        $this->db->run('UPDATE memberships SET all_stores=1 WHERE id=?',[Id::bytes($actor->membershipId)]);try{$this->read($actor,$this->context($actor,$this->store($actor),$id));self::fail('Expected unbound store check.');}catch(AccessDenied){self::addToAssertionCount(1);}self::assertSame(0,$this->calls);
    }
    public function testRevocationDuringNetworkDiscardsResultWithoutReadAudit(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=$this->connect($actor,$store);$this->duringCall=fn()=>$this->service()->revoke($actor,$id,2);
        try{$this->read($actor,$this->context($actor,$store,$id));self::fail('Expected post-network revocation check.');}catch(AccessDenied){self::assertSame(0,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_configuration_read'",[Id::bytes($actor->merchantId)])->fetchColumn());}
    }
    public function testCredentialRotationDuringNetworkDiscardsResult(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=$this->connect($actor,$store);$this->duringCall=fn()=>$this->service()->rotate($actor,$id,2,new Secrets(['clientId'=>'next@example.test','clientSecret'=>'next-synthetic-secret']));
        $this->expectException(AccessDenied::class);$this->read($actor,$this->context($actor,$store,$id));
    }
    public function testMembershipRevocationDuringNetworkDiscardsResult(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=$this->connect($actor,$store);$this->duringCall=fn()=>$this->db->run("UPDATE memberships SET status='disabled' WHERE id=?",[Id::bytes($actor->membershipId)]);
        $this->expectException(AccessDenied::class);$this->read($actor,$this->context($actor,$store,$id));
    }
    public function testSafeHttpErrorsAndRetryHint(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=$this->connect($actor,$store);$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');$body=['storeId'=>$store,'connectionId'=>$id,'version'=>2];
        $this->upstreamStatus=401;$response=$this->request($token,$body);self::assertSame(424,$response->getStatusCode());self::assertSame('{"error":"invoice_provider_authentication"}',$response->getContent());
        $this->upstreamStatus=429;$response=$this->request($token,$body);self::assertSame(503,$response->getStatusCode());self::assertSame('12',$response->headers->get('Retry-After'));self::assertStringNotContainsString('PRIVATE',(string)$response->getContent());
    }
}
