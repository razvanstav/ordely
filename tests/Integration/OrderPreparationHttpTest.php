<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;

use Ordely\Commerce\Infrastructure\OrderCipher;
use Ordely\Identity\Domain\TenantContext;
use Ordely\Identity\Infrastructure\Sessions;
use Ordely\Infrastructure\Http\Application;
use Ordely\Integrations\Domain\Secrets;
use Ordely\Integrations\Infrastructure\{Connections,KeyRing,SecretCipher};
use Ordely\Shared\Id;
use Ordely\Tests\Support\{DatabaseTestCase,IntegrationFixtures,PreparationFixtures as F};
use Symfony\Component\HttpFoundation\{Request,Response};

final class OrderPreparationHttpTest extends DatabaseTestCase
{
    private string $keyFile;
    private mixed $previousKeyFile;
    private SecretCipher $cipher;
    protected function setUp(): void
    {
        parent::setUp();$this->keyFile=dirname(__DIR__,2).'/var/preparation-key-'.Id::new().'.json';
        file_put_contents($this->keyFile,json_encode(['active'=>'test','keys'=>['test'=>base64_encode(random_bytes(32))]],JSON_THROW_ON_ERROR));
        $this->previousKeyFile=$_ENV['ORDELY_KEYRING_FILE']??null;$_ENV['ORDELY_KEYRING_FILE']=$this->keyFile;$this->cipher=new SecretCipher(KeyRing::fromEnvironment());
    }
    protected function tearDown(): void
    {
        if($this->previousKeyFile===null){unset($_ENV['ORDELY_KEYRING_FILE']);}else{$_ENV['ORDELY_KEYRING_FILE']=$this->previousKeyFile;}
        if(is_file($this->keyFile)){unlink($this->keyFile);}parent::tearDown();
    }
    /** @return array{string,string} */
    private function order(TenantContext $actor,string $store): array
    {
        $connections=new Connections($this->db,IntegrationFixtures::registry(),$this->cipher);
        $connection=$connections->create($actor,Id::new(),'fake-invoice','Synthetic invoice',new Secrets(['apiToken'=>'PRIVATE-SYNTHETIC-TOKEN']));$connections->bind($actor,$connection,1,$store);
        $run=Id::new();$order=Id::new();
        $this->db->run("INSERT INTO commerce_sync_runs(id,merchant_id,store_id,connection_id,provider_key,status) VALUES(?,?,?,?,'synthetic','completed')",[Id::bytes($run),Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($connection)]);
        $envelope=(new OrderCipher($this->cipher))->seal($actor->merchantId,$store,$order,F::order());
        $this->db->run("INSERT INTO commerce_records(id,merchant_id,store_id,provider_key,kind,external_id,document,content_hash,version,last_run_id) VALUES(?,?,?,'synthetic','order','synthetic-order',?,?,3,?)",[Id::bytes($order),Id::bytes($actor->merchantId),Id::bytes($store),$envelope,hash('sha256','synthetic',true),Id::bytes($run)]);
        return [$order,$connection];
    }
    private function request(string $token,string $store,string $order,string $method='GET'): Response
    {
        return (new Application(fn():\PDO=>$this->db->pdo))->handle(Request::create('https://localhost/api/invoice-preparation/'.$order.'?storeId='.$store,$method,[],['ordely_session'=>$token]));
    }
    private function token(TenantContext $actor): string { return (new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1'); }
    public function testReadPreparesLocalSnapshotWithoutWritesOrFiscalSideEffects(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);[$order]=$this->order($actor,$store);$token=$this->token($actor);
        $response=$this->request($token,$store,$order);self::assertSame(200,$response->getStatusCode(),(string)$response->getContent());
        self::assertStringContainsString('no-store',(string)$response->headers->get('Cache-Control'));$data=json_decode((string)$response->getContent(),true,flags:JSON_THROW_ON_ERROR);
        self::assertSame(3,$data['source']['version']);self::assertSame($store,$data['source']['storeId']);self::assertFalse($data['canIssue']);self::assertSame('SYNTHETIC-PREP-STREET',$data['customer']['address']['street']);
        foreach(['document_envelope','PRIVATE-SYNTHETIC-TOKEN','DO-NOT-USE-SHIPPING'] as $secret){self::assertStringNotContainsString($secret,(string)$response->getContent());}
        self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM invoice_drafts WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
        $audit=json_encode($this->db->run('SELECT safe_data FROM audit_logs WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchAll(),JSON_THROW_ON_ERROR);
        foreach(['SYNTHETIC-PREP','synthetic@example.test','24.20'] as $personal){self::assertStringNotContainsString($personal,$audit);}
        self::assertSame(405,$this->request($token,$store,$order,'POST')->getStatusCode());
    }
    public function testForeignTenantStoreAndRevokedGrantAreDenied(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);[$order]=$this->order($actor,$store);$token=$this->token($actor);$foreign=$this->tenant();
        self::assertSame(401,$this->request('',$store,$order)->getStatusCode());self::assertSame(403,$this->request($this->token($foreign),$store,$order)->getStatusCode());
        self::assertSame(403,$this->request($token,$this->store($actor),$order)->getStatusCode());
        foreach(['owner','admin','finance','operator','viewer'] as $role){$this->db->run('UPDATE memberships SET role=? WHERE id=?',[$role,Id::bytes($actor->membershipId)]);self::assertSame($role==='viewer'?403:200,$this->request($token,$store,$order)->getStatusCode());}
        $this->db->run("UPDATE memberships SET role='finance',all_stores=0 WHERE id=?",[Id::bytes($actor->membershipId)]);self::assertSame(403,$this->request($token,$store,$order)->getStatusCode());
        $this->db->run('INSERT INTO membership_store_grants(merchant_id,membership_id,store_id) VALUES(?,?,?)',[Id::bytes($actor->merchantId),Id::bytes($actor->membershipId),Id::bytes($store)]);self::assertSame(200,$this->request($token,$store,$order)->getStatusCode());
        $this->db->run('DELETE FROM membership_store_grants WHERE membership_id=?',[Id::bytes($actor->membershipId)]);self::assertSame(403,$this->request($token,$store,$order)->getStatusCode());
    }
    public function testProfileConnectionChangeIsReportedWithoutNetworkOrCredentials(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);[$order,$connection]=$this->order($actor,$store);$token=$this->token($actor);
        $profile=new Secrets(['companyId'=>'TEST009','companyName'=>'Synthetic seller','series'=>'TEST']);
        $envelope=$this->cipher->encrypt($actor->merchantId,$store,'invoice-profile:'.$store.':'.$connection.':2:1',$profile);
        $this->db->run('INSERT INTO invoice_profiles(merchant_id,store_id,connection_id,connection_version,version,profile_envelope) VALUES(?,?,?,2,1,?)',[Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($connection),$envelope]);
        $response=$this->request($token,$store,$order);self::assertSame(200,$response->getStatusCode());self::assertStringContainsString('"needsVerification":false',(string)$response->getContent());
        $this->db->run('UPDATE provider_connections SET version=version+1 WHERE id=?',[Id::bytes($connection)]);
        $response=$this->request($token,$store,$order);self::assertSame(200,$response->getStatusCode());self::assertStringContainsString('profile_needs_verification',(string)$response->getContent());
        $this->db->run('UPDATE commerce_records SET active=FALSE WHERE id=?',[Id::bytes($order)]);self::assertSame(403,$this->request($token,$store,$order)->getStatusCode());
    }
}
