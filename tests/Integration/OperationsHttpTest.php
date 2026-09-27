<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Core\Value\OperationKey;
use Ordely\Identity\Domain\Role;
use Ordely\Identity\Infrastructure\{Provisioner,Sessions};
use Ordely\Infrastructure\Http\Application;
use Ordely\Operations\Domain\{FailureCode,SafePayload,Scope};
use Ordely\Operations\Infrastructure\MySqlJobQueue;
use Ordely\Shared\Id;
use Ordely\Tests\Support\DatabaseTestCase;
use Symfony\Component\HttpFoundation\{Request,Response};

final class OperationsHttpTest extends DatabaseTestCase
{
    /** @param array<string,mixed> $body */
    private function request(string $path,string $token,string $method='GET',array $body=[],?string $key=null,bool $csrf=true): Response
    {
        $server=['CONTENT_TYPE'=>'application/json'];if($key!==null){$server['HTTP_IDEMPOTENCY_KEY']=$key;}if($csrf){$server['HTTP_X_CSRF_TOKEN']=Sessions::csrf($token);}
        return (new Application(fn():\PDO=>$this->db->pdo))->handle(Request::create('https://localhost'.$path,$method,[],['ordely_session'=>$token],[],$server,json_encode((object)$body,JSON_THROW_ON_ERROR)));
    }
    public function testHttpReplayCreatesOneStoreAndRejectsChangedBodyOrRevokedRole(): void
    {
        $actor=$this->tenant();$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');
        $body=['name'=>'Test idempotent','platform'=>'manual'];$first=$this->request('/api/stores',$token,'POST',$body,'same-key');$second=$this->request('/api/stores',$token,'POST',$body,'same-key');
        self::assertSame(201,$first->getStatusCode());self::assertSame($first->getContent(),$second->getContent());
        self::assertSame(409,$this->request('/api/stores',$token,'POST',['name'=>'Changed','platform'=>'manual'],'same-key')->getStatusCode());
        self::assertSame(400,$this->request('/api/stores',$token,'POST',$body)->getStatusCode());
        self::assertSame(1,(int)$this->db->run('SELECT COUNT(*) FROM stores WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
        $this->db->run("UPDATE memberships SET role='viewer' WHERE id=?",[Id::bytes($actor->membershipId)]);
        self::assertSame(403,$this->request('/api/stores',$token,'POST',$body,'same-key')->getStatusCode());
    }
    public function testInspectionRespectsTenantStoreGrantsAndManagerRole(): void
    {
        $actor=$this->tenant(Role::Admin,false);$store=$this->store($actor);$hidden=$this->store($actor);$foreign=$this->tenant();$foreignStore=$this->store($foreign);
        (new Provisioner($this->db))->grantStore($actor->merchantId,$actor->membershipId,$store);$queue=new MySqlJobQueue($this->db);
        $own=$queue->enqueue(new Scope($actor->merchantId,$store),'test.work',new SafePayload(),new OperationKey('own'));
        $queue->enqueue(new Scope($actor->merchantId,$hidden),'test.work',new SafePayload(),new OperationKey('hidden'));
        $queue->enqueue(new Scope($foreign->merchantId,$foreignStore),'test.work',new SafePayload(),new OperationKey('foreign'));
        $token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');
        $response=$this->request('/api/operations',$token);self::assertSame(200,$response->getStatusCode());
        $data=json_decode((string)$response->getContent(),true,flags:JSON_THROW_ON_ERROR);self::assertCount(1,$data['jobs']);self::assertSame($own,$data['jobs'][0]['id']);
        self::assertStringNotContainsString('lease_owner',(string)$response->getContent());
        $this->db->run("UPDATE memberships SET role='viewer' WHERE id=?",[Id::bytes($actor->membershipId)]);
        self::assertSame(403,$this->request('/api/operations',$token)->getStatusCode());
    }
    public function testRetryRequiresCsrfAndOwnTenant(): void
    {
        $actor=$this->tenant();$foreign=$this->tenant();$queue=new MySqlJobQueue($this->db);
        $id=$queue->enqueue(new Scope($actor->merchantId,$this->store($actor)),'test.work',new SafePayload(),new OperationKey('job'));
        $job=$queue->claim($actor->merchantId);self::assertNotNull($job);$queue->fail($job,FailureCode::Invalid);
        $sessions=new Sessions($this->db);$token=$sessions->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');$other=$sessions->login($foreign->userId.'@example.test',self::PASSWORD,'127.0.0.1');
        self::assertSame(403,$this->request('/api/jobs/'.$id.'/retry',$token,'POST',csrf:false)->getStatusCode());
        self::assertSame(409,$this->request('/api/jobs/'.$id.'/retry',$other,'POST')->getStatusCode());
        self::assertSame(200,$this->request('/api/jobs/'.$id.'/retry',$token,'POST')->getStatusCode());
    }
    public function testFailedLocalCommandLeavesNoIdempotencyReceiptOrBusinessEffect(): void
    {
        $actor=$this->tenant();
        try{
            (new \Ordely\Operations\Infrastructure\IdempotencyGate($this->db))->run($actor,null,'stores.create','stores.manage',new OperationKey('rollback'),[],function()use($actor):SafePayload{
                (new \Ordely\Identity\Infrastructure\StoreRepository($this->db))->create($actor,'Rolled back','manual');throw new \RuntimeException('local failure');
            });
        }catch(\RuntimeException){
            foreach(['stores','idempotency_requests','outbox_events','audit_logs'] as $table){self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM '.$table.' WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());}
        }
    }
}
