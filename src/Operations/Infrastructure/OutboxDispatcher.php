<?php
declare(strict_types=1);
namespace Ordely\Operations\Infrastructure;
use Ordely\Core\Value\OperationKey;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\{SafePayload,Scope};
use Ordely\Shared\Id;

final readonly class OutboxDispatcher
{
    /** @param array<string,list<string>> $consumers event type => registered job types */
    public function __construct(private Sql $db,private array $consumers) {}
    public function dispatchOne(?string $merchantId=null): bool
    {
        if($this->consumers===[]){return false;}
        return $this->db->transaction(function()use($merchantId):bool{
            $types=array_keys($this->consumers);$marks=implode(',',array_fill(0,count($types),'?'));
            $filter=$merchantId===null?'':' AND e.merchant_id=?';$params=$types;if($merchantId!==null){$params[]=Id::bytes($merchantId);}
            $event=$this->db->one("SELECT e.* FROM outbox_events e JOIN merchants m ON m.id=e.merchant_id AND m.status='active'
                LEFT JOIN stores s ON s.merchant_id=e.merchant_id AND s.id=e.store_id
                WHERE e.published_at IS NULL AND (e.store_id IS NULL OR s.status='active') AND e.event_type IN (".$marks.')'.$filter.
                ' ORDER BY e.occurred_at,e.id LIMIT 1 FOR UPDATE OF e SKIP LOCKED',$params);
            if($event===null){return false;}
            if((int)$event['schema_version']!==1){throw new \InvalidArgumentException('Unsupported event schema.');}
            $scope=new Scope(bin2hex((string)$event['merchant_id']),$event['store_id']===null?null:bin2hex((string)$event['store_id']));
            $id=bin2hex((string)$event['id']);$queue=new MySqlJobQueue($this->db);
            foreach($this->consumers[(string)$event['event_type']] as $consumer){
                $job=$queue->enqueue($scope,$consumer,new SafePayload(['event_id'=>$id]),new OperationKey('event:'.$id.':'.$consumer));
                $this->db->run('INSERT INTO event_deliveries(merchant_id,event_id,consumer,job_id) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE job_id=job_id',[(string)$event['merchant_id'],(string)$event['id'],$consumer,Id::bytes($job)]);
            }
            $this->db->run('UPDATE outbox_events SET published_at=UTC_TIMESTAMP(6) WHERE id=?',[(string)$event['id']]);return true;
        });
    }
}
