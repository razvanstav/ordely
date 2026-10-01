<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Identity\Infrastructure\Sessions;
use Ordely\Infrastructure\Http\Application;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Shared\Id;
use Ordely\Tests\Support\{DatabaseTestCase,IssueFixtures as F};
use Symfony\Component\HttpFoundation\{Request,Response};

final class InvoiceIssueIntentHttpTest extends DatabaseTestCase
{
    private string $file;private mixed $previous;private SecretCipher $cipher;
    protected function setUp(): void {parent::setUp();$this->file=dirname(__DIR__,2).'/var/issue-key-'.Id::new().'.json';file_put_contents($this->file,json_encode(['active'=>'test','keys'=>['test'=>base64_encode(random_bytes(32))]],JSON_THROW_ON_ERROR));$this->previous=$_ENV['ORDELY_KEYRING_FILE']??null;$_ENV['ORDELY_KEYRING_FILE']=$this->file;$this->cipher=new SecretCipher(KeyRing::fromEnvironment());}
    protected function tearDown(): void {if($this->previous===null){unset($_ENV['ORDELY_KEYRING_FILE']);}else{$_ENV['ORDELY_KEYRING_FILE']=$this->previous;}unlink($this->file);parent::tearDown();}
    /** @param array<string,mixed>|null $body */
    private function request(string $token,string $store,?string $id=null,?array $body=null,bool $csrf=true,string $origin='https://localhost',?string $method=null): Response
    {
        $path='/api/invoice-issue-intents'.($id===null?'':'/'.$id).'?storeId='.$store;
        return (new Application(fn():\PDO=>$this->db->pdo))->handle(Request::create('https://localhost'.$path,$method??($body===null?'GET':'POST'),[],['ordely_session'=>$token],[],['CONTENT_TYPE'=>'application/json','HTTP_ORIGIN'=>$origin,'HTTP_X_CSRF_TOKEN'=>$csrf?Sessions::csrf($token):''],$body===null?null:json_encode($body,JSON_THROW_ON_ERROR)));
    }
    public function testPrepareReadRetryAndAllAuthorizationBoundariesWithoutExecutionRoute(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$fixture=F::ready($this->db,$actor,$store,$this->cipher);$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');$body=['storeId'=>$store,'orderId'=>$fixture['order'],'expectedVersion'=>2];
        self::assertSame(403,$this->request($token,$store,body:$body,csrf:false)->getStatusCode());self::assertSame(403,$this->request($token,$store,body:$body,origin:'https://foreign.test')->getStatusCode());
        self::assertSame(400,$this->request($token,$store,body:$body+['snapshot'=>[]])->getStatusCode());self::assertSame(400,$this->request($token,$store,body:array_replace($body,['expectedVersion'=>'2']))->getStatusCode());
        self::assertSame(409,$this->request($token,$store,body:array_replace($body,['expectedVersion'=>1]))->getStatusCode());
        $first=$this->request($token,$store,body:$body);self::assertSame(200,$first->getStatusCode(),(string)$first->getContent());$data=json_decode((string)$first->getContent(),true,flags:JSON_THROW_ON_ERROR);$id=$data['intent']['id'];self::assertSame('PREPARED',$data['intent']['status']);self::assertFalse($data['intent']['executionEnabled']);
        self::assertSame($first->getContent(),$this->request($token,$store,body:$body)->getContent());self::assertStringNotContainsString('SYNTHETIC-PREP',(string)$first->getContent());
        foreach(['owner','admin','finance','operator','viewer'] as $role){$this->db->run('UPDATE memberships SET role=? WHERE id=?',[$role,Id::bytes($actor->membershipId)]);self::assertSame(in_array($role,['operator','viewer'],true)?403:200,$this->request($token,$store,body:$body)->getStatusCode());self::assertSame($role==='viewer'?403:200,$this->request($token,$store,$id)->getStatusCode());}
        $this->db->run("UPDATE memberships SET role='owner' WHERE id=?",[Id::bytes($actor->membershipId)]);
        self::assertSame(403,$this->request($token,$this->store($actor),$id)->getStatusCode());$foreign=$this->tenant();$foreignToken=(new Sessions($this->db))->login($foreign->userId.'@example.test',self::PASSWORD,'127.0.0.1');self::assertSame(403,$this->request($foreignToken,$store,$id)->getStatusCode());
        $this->db->run('UPDATE memberships SET all_stores=0 WHERE id=?',[Id::bytes($actor->membershipId)]);self::assertSame(403,$this->request($token,$store,$id)->getStatusCode());self::assertSame(403,$this->request($token,$store,body:$body)->getStatusCode());
        self::assertSame(405,$this->request($token,$store,$id,$body,method:'POST')->getStatusCode());
        self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM external_operations WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM jobs WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
    }
    public function testIncompleteOrChangedDraftCannotCreateAnIntent(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$fixture=F::ready($this->db,$actor,$store,$this->cipher);$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');
        $drafts=F::drafts($this->db,$this->cipher);$drafts->complete($actor,$store,$fixture['order'],2,[]);$body=['storeId'=>$store,'orderId'=>$fixture['order'],'expectedVersion'=>3];self::assertSame(400,$this->request($token,$store,body:$body)->getStatusCode());
        $this->db->run('UPDATE commerce_records SET version=4 WHERE id=?',[Id::bytes($fixture['order'])]);self::assertSame(409,$this->request($token,$store,body:$body)->getStatusCode());self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM invoice_issue_intents WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
    }
}
