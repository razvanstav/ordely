<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Identity\Infrastructure\Sessions;
use Ordely\Infrastructure\Http\Application;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Shared\Id;
use Ordely\Tests\Support\{DatabaseTestCase,PreparationFixtures as F};
use Symfony\Component\HttpFoundation\{Request,Response};
final class OrderDraftHttpTest extends DatabaseTestCase
{
    private string $file;private mixed $previous;private SecretCipher $cipher;
    protected function setUp(): void {parent::setUp();$this->file=dirname(__DIR__,2).'/var/preparation-key-'.Id::new().'.json';file_put_contents($this->file,json_encode(['active'=>'test','keys'=>['test'=>base64_encode(random_bytes(32))]],JSON_THROW_ON_ERROR));$this->previous=$_ENV['ORDELY_KEYRING_FILE']??null;$_ENV['ORDELY_KEYRING_FILE']=$this->file;$this->cipher=new SecretCipher(KeyRing::fromEnvironment());}
    protected function tearDown(): void {if($this->previous===null){unset($_ENV['ORDELY_KEYRING_FILE']);}else{$_ENV['ORDELY_KEYRING_FILE']=$this->previous;}unlink($this->file);parent::tearDown();}
    /** @param array<string,mixed>|null $body */
    private function request(string $token,string $store,?string $order,?array $body=null,bool $csrf=true,string $origin='https://localhost',?string $method=null): Response
    {
        $path='/api/invoice-order-drafts'.($order===null?'':'/'.$order).'?storeId='.$store;
        return (new Application(fn():\PDO=>$this->db->pdo))->handle(Request::create('https://localhost'.$path,$method??($body===null?'GET':'POST'),[],['ordely_session'=>$token],[],['CONTENT_TYPE'=>'application/json','HTTP_ORIGIN'=>$origin,'HTTP_X_CSRF_TOKEN'=>$csrf?Sessions::csrf($token):''], $body===null?null:json_encode($body,JSON_THROW_ON_ERROR)));
    }
    public function testCompletionIsEncryptedVersionedProtectedAndPreservedOnRefresh(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$order=F::persist($this->db,$actor,$store,$this->cipher);$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');
        $create=['storeId'=>$store,'expectedVersion'=>0,'orderVersion'=>3,'profileVersion'=>0];$body=['storeId'=>$store,'expectedVersion'=>1,'details'=>F::fiscalDetails()];
        self::assertSame(409,$this->request($token,$store,$order,$body,method:'PUT')->getStatusCode());self::assertSame(200,$this->request($token,$store,$order,$create)->getStatusCode());
        self::assertSame(403,$this->request($token,$store,$order,$body,false,method:'PUT')->getStatusCode());self::assertSame(403,$this->request($token,$store,$order,$body,true,'https://foreign.test','PUT')->getStatusCode());
        foreach(['operator','viewer'] as $role){$this->db->run('UPDATE memberships SET role=? WHERE id=?',[$role,Id::bytes($actor->membershipId)]);self::assertSame(403,$this->request($token,$store,$order,$body,method:'PUT')->getStatusCode());}
        $this->db->run("UPDATE memberships SET role='finance' WHERE id=?",[Id::bytes($actor->membershipId)]);
        foreach(range(1,2) as $_){$response=$this->request($token,$store,$order,$body,method:'PUT');self::assertSame(200,$response->getStatusCode(),(string)$response->getContent());self::assertSame('{"version":2}',$response->getContent());}
        $read=json_decode((string)$this->request($token,$store,$order)->getContent(),true,flags:JSON_THROW_ON_ERROR);self::assertFalse($read['draft']['sourceChanged']);self::assertFalse($read['draft']['fiscal']['canIssue']);self::assertSame('21.0000',$read['draft']['fiscal']['lines'][0]['taxRate']);
        self::assertStringNotContainsString('SYNTHETIC-TAX-ID',(string)$this->db->run('SELECT snapshot_envelope FROM invoice_order_drafts WHERE order_id=?',[Id::bytes($order)])->fetchColumn());
        $different=$body;$different['details']['lineDefaults']['unit']='kg';self::assertSame(409,$this->request($token,$store,$order,$different,method:'PUT')->getStatusCode());
        $invalid=$body;$invalid['expectedVersion']=2;$invalid['details']['customer']['street']='OTHER';self::assertSame(400,$this->request($token,$store,$order,$invalid,method:'PUT')->getStatusCode());
        $this->db->run('UPDATE commerce_records SET version=4 WHERE id=?',[Id::bytes($order)]);self::assertSame(409,$this->request($token,$store,$order,$body,method:'PUT')->getStatusCode());
        $create['expectedVersion']=2;$create['orderVersion']=4;self::assertSame('{"version":3}',$this->request($token,$store,$order,$create)->getContent());
        $read=json_decode((string)$this->request($token,$store,$order)->getContent(),true,flags:JSON_THROW_ON_ERROR);self::assertSame('SYNTHETIC-TAX-ID',$read['draft']['fiscal']['customer']['taxId']);self::assertFalse($read['draft']['sourceChanged']);
        $wrongStore=$body;$wrongStore['storeId']=$this->store($actor);self::assertSame(403,$this->request($token,$wrongStore['storeId'],$order,$wrongStore,method:'PUT')->getStatusCode());
        $foreign=$this->tenant();$foreignToken=(new Sessions($this->db))->login($foreign->userId.'@example.test',self::PASSWORD,'127.0.0.1');self::assertSame(403,$this->request($foreignToken,$store,$order,$body,method:'PUT')->getStatusCode());
        $this->db->run('UPDATE memberships SET all_stores=0 WHERE id=?',[Id::bytes($actor->membershipId)]);self::assertSame(403,$this->request($token,$store,$order,$body,method:'PUT')->getStatusCode());
        self::assertSame(3,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_preparation_saved'",[Id::bytes($actor->merchantId)])->fetchColumn());
        self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM outbox_events WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
    }
    public function testSaveFreezeRefreshRetryEncryptionAndPrivacyDeletion(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$order=F::persist($this->db,$actor,$store,$this->cipher);$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');
        $body=['storeId'=>$store,'expectedVersion'=>0,'orderVersion'=>3,'profileVersion'=>0];
        foreach(range(1,2) as $_){$r=$this->request($token,$store,$order,$body);self::assertSame(200,$r->getStatusCode(),(string)$r->getContent());self::assertSame('{"version":1}',$r->getContent());}
        self::assertSame(1,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_preparation_saved'",[Id::bytes($actor->merchantId)])->fetchColumn());
        $raw=$this->db->run('SELECT snapshot_envelope FROM invoice_order_drafts WHERE order_id=?',[Id::bytes($order)])->fetchColumn();self::assertStringNotContainsString('SYNTHETIC-PREP',(string)$raw);
        $read=json_decode((string)$this->request($token,$store,$order)->getContent(),true,flags:JSON_THROW_ON_ERROR);self::assertFalse($read['draft']['sourceChanged']);self::assertFalse($read['draft']['snapshot']['canIssue']);
        self::assertStringContainsString('TEST-PREP-09',(string)$this->request($token,$store,null)->getContent());
        $this->db->run('UPDATE commerce_records SET version=4 WHERE id=?',[Id::bytes($order)]);
        $read=json_decode((string)$this->request($token,$store,$order)->getContent(),true,flags:JSON_THROW_ON_ERROR);self::assertTrue($read['draft']['sourceChanged']);self::assertSame(3,$read['draft']['snapshot']['source']['version']);
        self::assertSame(409,$this->request($token,$store,$order,$body)->getStatusCode());$body['orderVersion']=4;$body['expectedVersion']=1;
        self::assertSame(200,$this->request($token,$store,$order,$body)->getStatusCode());$body['expectedVersion']=0;self::assertSame(409,$this->request($token,$store,$order,$body)->getStatusCode());
        self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM invoice_drafts WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
        $this->db->run('DELETE FROM commerce_records WHERE id=?',[Id::bytes($order)]);self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM invoice_order_drafts WHERE order_id=?',[Id::bytes($order)])->fetchColumn());
    }
    public function testWriteProtectionAndCurrentPermissions(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$order=F::persist($this->db,$actor,$store,$this->cipher);$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');$body=['storeId'=>$store,'expectedVersion'=>0,'orderVersion'=>3,'profileVersion'=>0];
        self::assertSame(403,$this->request($token,$store,$order,$body,false)->getStatusCode());self::assertSame(403,$this->request($token,$store,$order,$body,true,'https://foreign.test')->getStatusCode());
        self::assertSame(400,$this->request($token,$store,$order,$body+['snapshot'=>F::order()])->getStatusCode());
        foreach(['owner','admin','finance','operator','viewer'] as $role){$this->db->run('UPDATE memberships SET role=? WHERE id=?',[$role,Id::bytes($actor->membershipId)]);self::assertSame(in_array($role,['operator','viewer'],true)?403:200,$this->request($token,$store,$order,$body)->getStatusCode());self::assertSame($role==='viewer'?403:200,$this->request($token,$store,$order)->getStatusCode());}
        $this->db->run("UPDATE memberships SET role='finance',all_stores=0 WHERE id=?",[Id::bytes($actor->membershipId)]);self::assertSame(403,$this->request($token,$store,$order,$body)->getStatusCode());
        $foreign=$this->tenant();$foreignToken=(new Sessions($this->db))->login($foreign->userId.'@example.test',self::PASSWORD,'127.0.0.1');self::assertSame(403,$this->request($foreignToken,$store,$order)->getStatusCode());
        self::assertSame(0,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_preparation_saved' AND safe_data LIKE '%SYNTHETIC%'",[Id::bytes($actor->merchantId)])->fetchColumn());
    }
    public function testSavedProfileRetryAndConnectionInvalidation(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$order=F::persist($this->db,$actor,$store,$this->cipher);
        $connection=bin2hex((string)$this->db->run('SELECT connection_id FROM commerce_sync_runs WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
        (new \Ordely\Integrations\Infrastructure\Connections($this->db,\Ordely\Tests\Support\IntegrationFixtures::registry(),$this->cipher))->bind($actor,$connection,1,$store);
        $envelope=$this->cipher->encrypt($actor->merchantId,$store,'invoice-profile:'.$store.':'.$connection.':2:1',new \Ordely\Integrations\Domain\Secrets(['companyId'=>'TEST009','companyName'=>'Synthetic seller','series'=>'TEST']));
        $this->db->run('INSERT INTO invoice_profiles(merchant_id,store_id,connection_id,connection_version,version,profile_envelope) VALUES(?,?,?,2,1,?)',[Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($connection),$envelope]);
        $token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');$body=['storeId'=>$store,'expectedVersion'=>0,'orderVersion'=>3,'profileVersion'=>1];
        foreach(range(1,2) as $_){$r=$this->request($token,$store,$order,$body);self::assertSame(200,$r->getStatusCode(),(string)$r->getContent());self::assertSame('{"version":1}',$r->getContent());}
        $read=json_decode((string)$this->request($token,$store,$order)->getContent(),true,flags:JSON_THROW_ON_ERROR);self::assertFalse($read['draft']['sourceChanged']);
        $this->db->run('UPDATE provider_connections SET version=3 WHERE id=?',[Id::bytes($connection)]);
        $read=json_decode((string)$this->request($token,$store,$order)->getContent(),true,flags:JSON_THROW_ON_ERROR);self::assertTrue($read['draft']['sourceChanged']);self::assertFalse($read['draft']['snapshot']['seller']['needsVerification']);
        self::assertSame(409,$this->request($token,$store,$order,$body)->getStatusCode());$body['expectedVersion']=1;
        self::assertSame('{"version":2}',$this->request($token,$store,$order,$body)->getContent());
        self::assertSame(403,$this->request($token,$this->store($actor),$order)->getStatusCode());
    }
}
