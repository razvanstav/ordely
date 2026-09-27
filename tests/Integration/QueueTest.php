<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Core\Value\OperationKey;
use Ordely\Identity\Domain\AccessDenied;
use Ordely\Identity\Infrastructure\StoreRepository;
use Ordely\Operations\Application\{JobHandler,Worker};
use Ordely\Operations\Domain\{Conflict,EventType,FailureCode,JobLease,LeaseLost,SafePayload,Scope};
use Ordely\Operations\Infrastructure\{Inbox,MySqlJobQueue,OutboxDispatcher,StoreEventHandler};
use Ordely\Shared\Id;
use Ordely\Tests\Support\DatabaseTestCase;

final class QueueTest extends DatabaseTestCase
{
    public function testExpiredWorkerCannotAcknowledgeAnotherWorkersLease(): void
    {
        $tenant=$this->tenant();$scope=new Scope($tenant->merchantId,$this->store($tenant));$queue=new MySqlJobQueue($this->db);
        $id=$queue->enqueue($scope,'test.work',new SafePayload(),new OperationKey('job'));
        $first=$queue->claim($tenant->merchantId);self::assertNotNull($first);$queue->heartbeat($first,120);
        $this->db->run('UPDATE jobs SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[Id::bytes($id)]);
        $second=$queue->claim($tenant->merchantId);self::assertNotNull($second);self::assertSame($id,$second->id);self::assertGreaterThan($first->fence,$second->fence);self::assertNotSame($first->owner,$second->owner);
        try{$queue->acknowledge($first);self::fail('Expected stale worker rejection.');}catch(LeaseLost){self::addToAssertionCount(1);}
        $queue->acknowledge($second);
        self::assertSame('SUCCEEDED',$this->db->run('SELECT status FROM jobs WHERE id=?',[Id::bytes($id)])->fetchColumn());
        self::assertSame(2,(int)$this->db->run('SELECT COUNT(*) FROM job_attempts WHERE job_id=?',[Id::bytes($id)])->fetchColumn());
        self::assertNull($queue->claim($tenant->merchantId));
    }
    public function testRetryAfterBudgetAndManualRequeueAreAudited(): void
    {
        $tenant=$this->tenant();$scope=new Scope($tenant->merchantId,$this->store($tenant));$queue=new MySqlJobQueue($this->db);
        $id=$queue->enqueue($scope,'test.work',new SafePayload(),new OperationKey('job'),2);
        $first=$queue->claim($tenant->merchantId);self::assertNotNull($first);$queue->fail($first,FailureCode::Transient,120);
        self::assertGreaterThanOrEqual(119,(int)$this->db->run('SELECT TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(6),available_at) FROM jobs WHERE id=?',[Id::bytes($id)])->fetchColumn());
        self::assertNull($queue->claim($tenant->merchantId));
        $this->db->run('UPDATE jobs SET available_at=UTC_TIMESTAMP(6) WHERE id=?',[Id::bytes($id)]);
        $queue=new MySqlJobQueue($this->db);
        $second=$queue->claim($tenant->merchantId);self::assertNotNull($second);$queue->fail($second,FailureCode::Transient);
        self::assertSame('DEAD',$this->db->run('SELECT status FROM jobs WHERE id=?',[Id::bytes($id)])->fetchColumn());
        $queue->requeue($tenant,$id);
        self::assertSame(1,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE entity_id=? AND action='job_requeued'",[Id::bytes($id)])->fetchColumn());
        $third=$queue->claim($tenant->merchantId);self::assertNotNull($third);$queue->fail($third,FailureCode::Unknown);
        $this->db->run("UPDATE memberships SET role='viewer' WHERE id=?",[Id::bytes($tenant->membershipId)]);
        $this->expectException(AccessDenied::class);$queue->requeue($tenant,$id);
    }
    public function testInactiveScopeIsNotClaimedAndForgedStoreCannotAcknowledge(): void
    {
        $tenant=$this->tenant();$store=$this->store($tenant);$otherStore=$this->store($tenant);$scope=new Scope($tenant->merchantId,$store);$queue=new MySqlJobQueue($this->db);
        $queue->enqueue($scope,'test.work',new SafePayload(),new OperationKey('job'));
        $job=$queue->claim($tenant->merchantId);self::assertNotNull($job);
        $forged=new JobLease(new Scope($tenant->merchantId,$otherStore),$job->id,$job->type,$job->payload,$job->owner,$job->fence,$job->attempt,$job->maximum);
        try{$queue->acknowledge($forged);self::fail('Expected store mismatch rejection.');}catch(LeaseLost){self::addToAssertionCount(1);}
        $this->db->run("UPDATE stores SET status='disabled' WHERE id=?",[Id::bytes($store)]);
        try{$queue->guard($job);self::fail('Expected inactive scope.');}catch(AccessDenied){self::addToAssertionCount(1);}
        $this->db->run('UPDATE jobs SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[Id::bytes($job->id)]);
        self::assertNull($queue->claim($tenant->merchantId));
    }
    public function testInboxDeduplicatesAndRejectsChangedDeliveryWithoutExtraJobs(): void
    {
        $tenant=$this->tenant();$scope=new Scope($tenant->merchantId,$this->store($tenant));$inbox=new Inbox($this->db);$source=new OperationKey('fake-connection');$delivery=new OperationKey('delivery-1');
        $first=$inbox->receive($scope,$source,$delivery,hash('sha256','original'),new SafePayload(),'test.inbox');
        self::assertSame($first,$inbox->receive($scope,$source,$delivery,hash('sha256','original'),new SafePayload(),'test.inbox'));
        try{$inbox->receive($scope,$source,$delivery,hash('sha256','changed'),new SafePayload(),'test.inbox');self::fail('Expected delivery mismatch.');}catch(Conflict){self::addToAssertionCount(1);}
        self::assertSame(1,(int)$this->db->run('SELECT COUNT(*) FROM jobs WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());
        $other=$this->tenant();$otherScope=new Scope($other->merchantId,$this->store($other));
        self::assertNotSame($first,$inbox->receive($otherScope,$source,$delivery,hash('sha256','original'),new SafePayload(),'test.inbox'));
    }
    public function testOutboxReplayAndConsumerCrashDoNotDuplicateLocalEffect(): void
    {
        $tenant=$this->tenant();(new StoreRepository($this->db))->create($tenant,'Test','manual');
        $dispatcher=new OutboxDispatcher($this->db,[EventType::StoreCreated->value=>['store.observe']]);
        self::assertTrue($dispatcher->dispatchOne($tenant->merchantId));self::assertFalse($dispatcher->dispatchOne($tenant->merchantId));
        $this->db->run('UPDATE outbox_events SET published_at=NULL WHERE merchant_id=?',[Id::bytes($tenant->merchantId)]);
        self::assertTrue($dispatcher->dispatchOne($tenant->merchantId));
        self::assertSame(1,(int)$this->db->run('SELECT COUNT(*) FROM jobs WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());
        $queue=new MySqlJobQueue($this->db);$handler=new StoreEventHandler($this->db);$job=$queue->claim($tenant->merchantId);self::assertNotNull($job);
        $handler->handle($job);
        $this->db->run('UPDATE jobs SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[Id::bytes($job->id)]);
        self::assertSame('succeeded',(new Worker($queue,[$handler]))->once($tenant->merchantId));
        self::assertSame(1,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='event_consumed'",[Id::bytes($tenant->merchantId)])->fetchColumn());
        self::assertSame('idle',(new Worker($queue,[$handler]))->once($tenant->merchantId));
    }
    public function testWorkerRecordsSafeFailureOnlyAndRejectsUnregisteredHandlers(): void
    {
        $tenant=$this->tenant();$scope=new Scope($tenant->merchantId,$this->store($tenant));$queue=new MySqlJobQueue($this->db);
        $queue->enqueue($scope,'test.failure',new SafePayload(),new OperationKey('job'));
        $handler=new class implements JobHandler { public function type():string{return 'test.failure';} public function handle(JobLease $job):void{throw new \RuntimeException('PASSWORD-MUST-NOT-BE-SAVED');} };
        self::assertSame('dead',(new Worker($queue,[$handler]))->once($tenant->merchantId));
        self::assertSame('unknown',$this->db->run('SELECT last_error FROM jobs WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());
        $queue->enqueue($scope,'test.unknown',new SafePayload(),new OperationKey('job-2'));
        self::assertSame('dead',(new Worker($queue,[]))->once($tenant->merchantId));
        self::assertSame(1,(int)$this->db->run("SELECT COUNT(*) FROM jobs WHERE merchant_id=? AND last_error='handler_missing'",[Id::bytes($tenant->merchantId)])->fetchColumn());
    }
    public function testRestrictedAdminCannotRequeueMerchantWideJob(): void
    {
        $tenant=$this->tenant(\Ordely\Identity\Domain\Role::Admin,false);$queue=new MySqlJobQueue($this->db);
        $id=$queue->enqueue(new Scope($tenant->merchantId),'test.work',new SafePayload(),new OperationKey('merchant-job'));
        $job=$queue->claim($tenant->merchantId);self::assertNotNull($job);$queue->fail($job,FailureCode::Invalid);
        $this->expectException(AccessDenied::class);$queue->requeue($tenant,$id);
    }
}
