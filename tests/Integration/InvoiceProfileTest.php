<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Adapters\Oblio\OblioProvider;
use Ordely\Identity\Domain\TenantContext;
use Ordely\Identity\Infrastructure\Sessions;
use Ordely\Infrastructure\Http\Application;
use Ordely\Integrations\Application\{ProviderDefinition,ProviderRegistry};
use Ordely\Integrations\Domain\{ProviderKind,Secrets};
use Ordely\Integrations\Infrastructure\{Connections,KeyRing,SecretCipher};
use Ordely\Shared\Id;
use Ordely\Tests\Support\{DatabaseTestCase,OblioFixtures as F};
use Symfony\Component\HttpFoundation\{Request,Response};

final class InvoiceProfileTest extends DatabaseTestCase
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
        parent::setUp();$this->keyFile=dirname(__DIR__,2).'/var/profile-test-key-'.Id::new().'.json';
        file_put_contents($this->keyFile,json_encode(['active'=>'test','keys'=>['test'=>base64_encode(random_bytes(32))]],JSON_THROW_ON_ERROR));
        $this->previousKeyFile=$_ENV['ORDELY_KEYRING_FILE']??null;$_ENV['ORDELY_KEYRING_FILE']=$this->keyFile;
        $this->cipher=new SecretCipher(KeyRing::fromEnvironment());
        $this->registry=new ProviderRegistry(new ProviderDefinition('oblio','Oblio',ProviderKind::Invoice,['clientId','clientSecret'],fn(Secrets $secrets):OblioProvider=>new OblioProvider($secrets,function(string $method,string $path):array {
            ++$this->calls;if($this->duringCall!==null){$callback=$this->duringCall;$this->duringCall=null;$callback();}
            return $this->upstreamStatus===200?F::reply($path):['status'=>$this->upstreamStatus,'body'=>'PRIVATE upstream','retryAfter'=>12];
        }),OblioProvider::validateCredentials(...)));
    }
    protected function tearDown(): void
    {
        if($this->previousKeyFile===null){unset($_ENV['ORDELY_KEYRING_FILE']);}else{$_ENV['ORDELY_KEYRING_FILE']=$this->previousKeyFile;}
        if(is_file($this->keyFile)){unlink($this->keyFile);}parent::tearDown();
    }
    private function service(): Connections { return new Connections($this->db,$this->registry,$this->cipher); }
    /** @return array{TenantContext,string,string,string} */
    private function setupProfile(): array
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=$this->service()->create($actor,Id::new(),'oblio','Synthetic Oblio',F::credentials());$this->service()->bind($actor,$id,1,$store);
        $token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');return [$actor,$store,$id,$token];
    }
    /** @return array<string,mixed> */
    private function body(string $store,string $id,int $expected=0,int $version=2): array { return ['storeId'=>$store,'connectionId'=>$id,'version'=>$version,'expectedVersion'=>$expected,'companyId'=>'TEST001','series'=>'TEST']; }
    /** @param array<string,mixed> $body */
    private function request(string $token,array $body=[],string $method='POST',bool $csrf=true,?string $origin=null): Response
    {
        $server=['CONTENT_TYPE'=>'application/json'];if($csrf){$server['HTTP_X_CSRF_TOKEN']=Sessions::csrf($token);}if($origin!==null){$server['HTTP_ORIGIN']=$origin;}
        $url='https://localhost/api/invoice-profile'.($method==='GET'?'?storeId='.($body['storeId']??''):'');
        return (new Application(fn():\PDO=>$this->db->pdo,invoiceRegistry:$this->registry))->handle(Request::create($url,$method,[],['ordely_session'=>$token],[],$server,json_encode((object)$body,JSON_THROW_ON_ERROR)));
    }
    private function savedCount(string $merchant): int { return (int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_profile_saved'",[Id::bytes($merchant)])->fetchColumn(); }

    public function testSaveReadEncryptionAndIdenticalRetryAreScopedAndAudited(): void
    {
        [$actor,$store,$id,$token]=$this->setupProfile();$body=$this->body($store,$id);
        self::assertSame('{"profile":null}',$this->request($token,$body,'GET')->getContent());self::assertSame(0,$this->calls);
        foreach([1,2] as $_){$save=$this->request($token,$body);self::assertSame(200,$save->getStatusCode(),(string)$save->getContent());self::assertSame('{"version":1}',$save->getContent());}
        self::assertSame(1,$this->savedCount($actor->merchantId));
        $read=$this->request($token,$body,'GET');self::assertSame(200,$read->getStatusCode());self::assertStringContainsString('"companyName":"Synthetic company"',(string)$read->getContent());self::assertStringContainsString('"needsVerification":false',(string)$read->getContent());self::assertStringContainsString('no-store',(string)$read->headers->get('Cache-Control'));
        $envelope=(string)$this->db->run('SELECT profile_envelope FROM invoice_profiles WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn();
        $purpose='invoice-profile:'.$store.':'.$id.':2:1';self::assertSame(['companyId'=>'TEST001','companyName'=>'Synthetic company','series'=>'TEST'],$this->cipher->decrypt($actor->merchantId,$store,$purpose,$envelope)->reveal());
        self::assertStringNotContainsString('TEST001',$envelope);self::assertStringNotContainsString('Synthetic company',$envelope);
        $audit=serialize($this->db->run('SELECT safe_data FROM audit_logs WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchAll());self::assertStringNotContainsString('TEST001',$audit);self::assertStringNotContainsString('Synthetic company',$audit);
        foreach(['SYNTHETIC-oblio-secret','oblio@example.test','synthetic-access-token','profile_envelope'] as $secret){self::assertStringNotContainsString($secret,(string)$read->getContent());}
        self::assertSame(200,$this->request($token,$this->body($store,$id,1))->getStatusCode());self::assertSame(2,$this->savedCount($actor->merchantId));
        self::assertSame(409,$this->request($token,$body)->getStatusCode());self::assertSame(2,$this->savedCount($actor->merchantId));
    }
    public function testUnavailableSelectionDoesNotCreateProfile(): void
    {
        [$actor,$store,$id,$token]=$this->setupProfile();$body=$this->body($store,$id);
        self::assertSame(422,$this->request($token,[...$body,'companyId'=>'FOREIGN001'])->getStatusCode());
        self::assertSame(400,$this->request($token,[...$body,'series'=>'IGNORED'])->getStatusCode());self::assertSame(0,$this->savedCount($actor->merchantId));self::assertSame('{"profile":null}',$this->request($token,$body,'GET')->getContent());
    }
    public function testCsrfOriginForeignTenantAndUnboundStoreStopBeforeNetwork(): void
    {
        [$actor,$store,$id,$token]=$this->setupProfile();$body=$this->body($store,$id);$foreign=$this->tenant();$other=(new Sessions($this->db))->login($foreign->userId.'@example.test',self::PASSWORD,'127.0.0.1');
        self::assertSame(401,$this->request('',$body)->getStatusCode());self::assertSame(403,$this->request($token,$body,csrf:false)->getStatusCode());self::assertSame(403,$this->request($token,$body,origin:'https://foreign.test')->getStatusCode());
        self::assertSame(403,$this->request($other,$body)->getStatusCode());self::assertSame(403,$this->request($other,$body,'GET')->getStatusCode());self::assertSame(403,$this->request($token,[...$body,'storeId'=>$this->store($actor)])->getStatusCode());self::assertSame(0,$this->calls);
    }
    public function testCurrentRolesAndStoreGrantsControlReadsAndWrites(): void
    {
        [$actor,$store,$id,$token]=$this->setupProfile();$body=$this->body($store,$id);self::assertSame(200,$this->request($token,$body)->getStatusCode());$calls=$this->calls;
        foreach(['finance','operator','viewer'] as $role){$this->db->run('UPDATE memberships SET role=? WHERE id=?',[$role,Id::bytes($actor->membershipId)]);self::assertSame($role==='viewer'?403:200,$this->request($token,$body,'GET')->getStatusCode());self::assertSame(403,$this->request($token,$body)->getStatusCode());}
        $this->db->run("UPDATE memberships SET role='admin',all_stores=0 WHERE id=?",[Id::bytes($actor->membershipId)]);
        self::assertSame(403,$this->request($token,$body,'GET')->getStatusCode());self::assertSame(403,$this->request($token,$body)->getStatusCode());
        $this->db->run('INSERT INTO membership_store_grants(merchant_id,membership_id,store_id) VALUES(?,?,?)',[Id::bytes($actor->merchantId),Id::bytes($actor->membershipId),Id::bytes($store)]);
        self::assertSame(200,$this->request($token,$body,'GET')->getStatusCode());self::assertSame(403,$this->request($token,$body)->getStatusCode());self::assertSame($calls,$this->calls);
    }
    public function testConnectionChangeInvalidatesSelectionUntilExplicitReverification(): void
    {
        [$actor,$store,$id,$token]=$this->setupProfile();$body=$this->body($store,$id);self::assertSame(200,$this->request($token,$body)->getStatusCode());
        $this->service()->rotate($actor,$id,2);self::assertStringContainsString('"needsVerification":true',(string)$this->request($token,$body,'GET')->getContent());
        self::assertSame(403,$this->request($token,$this->body($store,$id,1))->getStatusCode());self::assertSame(200,$this->request($token,$this->body($store,$id,1,3))->getStatusCode());
        self::assertStringContainsString('"needsVerification":false',(string)$this->request($token,$body,'GET')->getContent());
        $this->service()->bind($actor,$id,3,$store,remove:true);self::assertStringContainsString('"needsVerification":true',(string)$this->request($token,$body,'GET')->getContent());self::assertSame(403,$this->request($token,$this->body($store,$id,2,4))->getStatusCode());
    }
    public function testRevocationDuringReadDiscardsSelection(): void
    {
        [$actor,$store,$id,$token]=$this->setupProfile();$this->duringCall=fn()=>$this->service()->revoke($actor,$id,2);
        self::assertSame(403,$this->request($token,$this->body($store,$id))->getStatusCode());self::assertSame(0,$this->savedCount($actor->merchantId));self::assertSame('{"profile":null}',$this->request($token,['storeId'=>$store],'GET')->getContent());
    }
    public function testRoleChangeDuringReadDiscardsSelection(): void
    {
        [$actor,$store,$id,$token]=$this->setupProfile();$this->duringCall=fn()=>$this->db->run("UPDATE memberships SET role='finance' WHERE id=?",[Id::bytes($actor->membershipId)]);
        self::assertSame(403,$this->request($token,$this->body($store,$id))->getStatusCode());self::assertSame(0,$this->savedCount($actor->merchantId));
    }
    public function testProviderFailurePreservesPreviousProfileAndReturnsSafeRetryHint(): void
    {
        [$actor,$store,$id,$token]=$this->setupProfile();$body=$this->body($store,$id);self::assertSame(200,$this->request($token,$body)->getStatusCode());$this->upstreamStatus=429;
        $response=$this->request($token,$this->body($store,$id,1));self::assertSame(503,$response->getStatusCode());self::assertSame('12',$response->headers->get('Retry-After'));self::assertStringNotContainsString('PRIVATE',(string)$response->getContent());self::assertSame(1,$this->savedCount($actor->merchantId));self::assertStringContainsString('"version":1',(string)$this->request($token,$body,'GET')->getContent());
    }
    public function testProfileCiphertextCannotBeMovedToAnotherStoreOrRevision(): void
    {
        [$actor,$store,$id,$token]=$this->setupProfile();self::assertSame(200,$this->request($token,$this->body($store,$id))->getStatusCode());
        $other=$this->store($actor);$this->db->run('INSERT INTO invoice_profiles(merchant_id,store_id,connection_id,connection_version,version,profile_envelope) SELECT merchant_id,?,connection_id,connection_version,version,profile_envelope FROM invoice_profiles WHERE merchant_id=? AND store_id=?',[Id::bytes($other),Id::bytes($actor->merchantId),Id::bytes($store)]);
        self::assertSame(500,$this->request($token,['storeId'=>$other],'GET')->getStatusCode());
        $this->db->run('UPDATE invoice_profiles SET version=2 WHERE merchant_id=? AND store_id=?',[Id::bytes($actor->merchantId),Id::bytes($store)]);self::assertSame(500,$this->request($token,['storeId'=>$store],'GET')->getStatusCode());
    }
}
