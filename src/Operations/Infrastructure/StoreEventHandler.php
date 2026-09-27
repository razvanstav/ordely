<?php
declare(strict_types=1);
namespace Ordely\Operations\Infrastructure;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Application\JobHandler;
use Ordely\Operations\Domain\{Actor,AuditAction,JobLease,SafePayload};
use Ordely\Shared\Id;

/** Reference local consumer: verifies the current store and records consumption atomically. */
final readonly class StoreEventHandler implements JobHandler
{
    public function __construct(private Sql $db) {}
    public function type(): string { return 'store.observe'; }
    public function handle(JobLease $job): void
    {
        $this->db->transaction(function()use($job):void{
            (new ScopeGuard($this->db))->active($job->scope);$event=$job->payload->id('event_id');
            $row=$this->db->one('SELECT d.processed_at,e.payload,e.schema_version,e.correlation_id,e.aggregate_id FROM event_deliveries d JOIN outbox_events e ON e.merchant_id=d.merchant_id AND e.id=d.event_id
                WHERE d.merchant_id=? AND d.event_id=? AND d.consumer=? AND d.job_id=? AND e.store_id <=> ? FOR UPDATE OF d',
                [Id::bytes($job->scope->merchantId),Id::bytes($event),$this->type(),Id::bytes($job->id),$job->scope->storeId===null?null:Id::bytes($job->scope->storeId)]);
            if($row===null||(int)$row['schema_version']!==1){throw new \InvalidArgumentException('Invalid event delivery.');}
            if($row['processed_at']!==null){return;}
            $data=SafePayload::fromJson((string)$row['payload']);
            if($data->id('store_id')!==$job->scope->storeId){throw new \InvalidArgumentException('Event store mismatch.');}
            (new AuditLog($this->db))->append($job->scope,Actor::system(),AuditAction::EventConsumed,bin2hex((string)$row['aggregate_id']),new SafePayload(['event_id'=>$event]),bin2hex((string)$row['correlation_id']));
            $this->db->run('UPDATE event_deliveries SET processed_at=UTC_TIMESTAMP(6) WHERE merchant_id=? AND event_id=? AND consumer=?',[Id::bytes($job->scope->merchantId),Id::bytes($event),$this->type()]);
        });
    }
}
