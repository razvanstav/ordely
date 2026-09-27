<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Core\Value\OperationKey;
use Ordely\Operations\Domain\{SafePayload,Scope};
use Ordely\Operations\Infrastructure\MySqlJobQueue;
use Ordely\Shared\Id;
use Ordely\Tests\Support\{CommittedDatabaseTestCase,ProcessHarness};

final class OperationsConcurrencyTest extends CommittedDatabaseTestCase
{
    public function testTwoPhpWorkersClaimDifferentJobs(): void
    {
        $tenant=$this->tenant();$scope=new Scope($tenant->merchantId,$this->store($tenant));$queue=new MySqlJobQueue($this->db);
        $ids=[$queue->enqueue($scope,'test.work',new SafePayload(),new OperationKey('one')),$queue->enqueue($scope,'test.work',new SafePayload(),new OperationKey('two'))];
        $results=ProcessHarness::together([['claim',$tenant->merchantId],['claim',$tenant->merchantId]]);$claimed=[];
        foreach($results as $result){self::assertSame(0,$result['exit'],$result['error']);$data=json_decode($result['output'],true,flags:JSON_THROW_ON_ERROR);$claimed[]=$data['id'];}
        sort($ids);sort($claimed);self::assertSame($ids,$claimed);
        self::assertSame(2,(int)$this->db->run("SELECT COUNT(*) FROM jobs WHERE merchant_id=? AND status='SUCCEEDED'",[Id::bytes($tenant->merchantId)])->fetchColumn());
    }
    public function testConcurrentHttpIdempotencyCreatesOneStoreAndOneOutboxEvent(): void
    {
        $tenant=$this->tenant();$command=['idempotency',$tenant->merchantId,$tenant->membershipId,$tenant->userId];
        $results=ProcessHarness::together([$command,$command]);$ids=[];
        foreach($results as $result){self::assertSame(0,$result['exit'],$result['error']);$data=json_decode($result['output'],true,flags:JSON_THROW_ON_ERROR);$ids[]=$data['id'];}
        self::assertSame($ids[0],$ids[1]);
        foreach(['stores','outbox_events','idempotency_requests'] as $table){self::assertSame(1,(int)$this->db->run('SELECT COUNT(*) FROM '.$table.' WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());}
    }
}
