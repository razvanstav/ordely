<?php
declare(strict_types=1);
namespace Ordely\Operations\Infrastructure;
use Ordely\Core\Value\OperationKey;
use Ordely\Identity\Domain\TenantContext;
use Ordely\Identity\Infrastructure\StoreRepository;
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Application\JobQueue;
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,FailureCode,JobLease,LeaseLost,SafePayload,Scope};
use Ordely\Shared\Id;

final readonly class MySqlJobQueue implements JobQueue
{
    public function __construct(private Sql $db) {}
    public function enqueue(Scope $scope,string $type,SafePayload $payload,OperationKey $dedupe,int $maximum=5): string
    {
        if(!preg_match('/^[a-z][a-z0-9_.]{0,63}$/D',$type)||$maximum<1||$maximum>25){throw new \InvalidArgumentException('Invalid job definition.');}
        (new ScopeGuard($this->db))->active($scope);
        return $this->db->transaction(function()use($scope,$type,$payload,$dedupe,$maximum):string{
            $id=Id::new();$hash=hash('sha256',$payload->json(),true);$key=hash('sha256',$scope->key().':'.$type.':'.$dedupe->value,true);
            $this->db->run('INSERT INTO jobs(id,merchant_id,store_id,type,payload,request_hash,dedupe_key,max_attempts) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',
                [Id::bytes($id),Id::bytes($scope->merchantId),$scope->storeId===null?null:Id::bytes($scope->storeId),$type,$payload->json(),$hash,$key,$maximum]);
            $row=$this->db->one('SELECT id,request_hash FROM jobs WHERE merchant_id=? AND dedupe_key=? FOR UPDATE',[Id::bytes($scope->merchantId),$key]);
            if($row===null){throw new \LogicException('Job insert missing.');}
            if(!hash_equals((string)$row['request_hash'],$hash)){throw new Conflict('job_payload_mismatch');}
            return bin2hex((string)$row['id']);
        });
    }
    public function claim(?string $merchantId=null,int $leaseSeconds=60): ?JobLease
    {
        $this->leaseDuration($leaseSeconds);
        if($merchantId!==null){Id::bytes($merchantId);}
        $this->recoverExpired($merchantId);
        return $this->db->transaction(function()use($merchantId,$leaseSeconds):?JobLease{
            $params=$merchantId===null?[]:[Id::bytes($merchantId)];
            $filter=$merchantId===null?'':' AND j.merchant_id=?';
            $row=$this->db->one("SELECT STRAIGHT_JOIN j.* FROM jobs j FORCE INDEX(jobs_claim) JOIN merchants m ON m.id=j.merchant_id AND m.status='active'
                LEFT JOIN stores s ON s.merchant_id=j.merchant_id AND s.id=j.store_id
                WHERE j.status='READY' AND j.attempt_count<j.max_attempts AND j.available_at<=UTC_TIMESTAMP(6) AND (j.store_id IS NULL OR s.status='active')".$filter.
                ' ORDER BY j.available_at,j.id LIMIT 1 FOR UPDATE OF j SKIP LOCKED',$params);
            if($row===null){return null;}
            $owner=Id::new();$fence=(int)$row['fencing_version']+1;$attempt=(int)$row['attempt_count']+1;
            $this->db->run("UPDATE jobs SET status='RUNNING',lease_owner=?,lease_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL ? SECOND),fencing_version=?,attempt_count=? WHERE id=?",
                [Id::bytes($owner),$leaseSeconds,$fence,$attempt,(string)$row['id']]);
            $this->db->run("INSERT INTO job_attempts(merchant_id,job_id,attempt_number,status) VALUES(?,?,?,'RUNNING')",[(string)$row['merchant_id'],(string)$row['id'],$attempt]);
            $lease=new JobLease($this->scope($row),bin2hex((string)$row['id']),(string)$row['type'],SafePayload::fromJson((string)$row['payload']),$owner,$fence,$attempt,(int)$row['max_attempts']);
            $this->audit($lease,AuditAction::JobClaimed);return $lease;
        });
    }
    public function guard(JobLease $lease): void
    {
        (new ScopeGuard($this->db))->active($lease->scope);
        $row=$this->db->one("SELECT id FROM jobs WHERE merchant_id=? AND store_id <=> ? AND id=? AND lease_owner=? AND fencing_version=? AND status='RUNNING' AND lease_until>UTC_TIMESTAMP(6)",$this->leaseParameters($lease));
        if($row===null){throw new LeaseLost('lease_lost');}
    }
    public function heartbeat(JobLease $lease,int $seconds=60): void
    {
        $this->leaseDuration($seconds);$this->guard($lease);
        $changed=$this->db->run("UPDATE jobs SET lease_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL ? SECOND) WHERE merchant_id=? AND store_id <=> ? AND id=? AND lease_owner=? AND fencing_version=? AND status='RUNNING' AND lease_until>UTC_TIMESTAMP(6)",[$seconds,...$this->leaseParameters($lease)])->rowCount();
        if($changed!==1){throw new LeaseLost('lease_lost');}
    }
    public function acknowledge(JobLease $lease): void
    {
        $this->finish($lease,null,null);
    }
    public function fail(JobLease $lease,FailureCode $error,?int $retryAfter=null): void
    {
        if($retryAfter!==null&&($retryAfter<0||$retryAfter>86400)){throw new \InvalidArgumentException('Invalid retry delay.');}
        $this->finish($lease,$error,$retryAfter);
    }
    private function finish(JobLease $lease,?FailureCode $error,?int $retryAfter): void
    {
        $this->db->transaction(function()use($lease,$error,$retryAfter):void{
            $retry=$error===FailureCode::Transient&&$lease->attempt<$lease->maximum;
            $base=min(3600,5*(2**min(10,max(0,$lease->attempt-1))));
            $delay=$retry?max($retryAfter ?? 0,$base+random_int(0,$base)):0;
            $status=$error===null?'SUCCEEDED':($retry?'READY':'DEAD');
            $changed=$this->db->run("UPDATE jobs SET status=?,available_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL ? SECOND),lease_owner=NULL,lease_until=NULL,last_error=?,completed_at=IF(?='READY',NULL,UTC_TIMESTAMP(6))
                WHERE merchant_id=? AND store_id <=> ? AND id=? AND lease_owner=? AND fencing_version=? AND status='RUNNING' AND lease_until>UTC_TIMESTAMP(6)",[$status,$delay,$error?->value,$status,...$this->leaseParameters($lease)])->rowCount();
            if($changed!==1){throw new LeaseLost('lease_lost');}
            $this->db->run('UPDATE job_attempts SET status=?,ended_at=UTC_TIMESTAMP(6),safe_error=? WHERE merchant_id=? AND job_id=? AND attempt_number=?',[$status,$error?->value,Id::bytes($lease->scope->merchantId),Id::bytes($lease->id),$lease->attempt]);
            $this->audit($lease,$error===null?AuditAction::JobSucceeded:($retry?AuditAction::JobRetried:AuditAction::JobDead));
        });
    }
    public function recoverExpired(?string $merchantId=null): int
    {
        return $this->db->transaction(function()use($merchantId):int{
            $params=$merchantId===null?[]:[Id::bytes($merchantId)];$filter=$merchantId===null?'':' AND merchant_id=?';
            $rows=$this->db->run("SELECT * FROM jobs WHERE status='RUNNING' AND lease_until<=UTC_TIMESTAMP(6)".$filter.' ORDER BY lease_until LIMIT 100 FOR UPDATE SKIP LOCKED',$params);
            $count=0;
            while($row=$rows->fetch()){
                $dead=(int)$row['attempt_count']>=(int)$row['max_attempts'];$status=$dead?'DEAD':'READY';
                $this->db->run("UPDATE jobs SET status=?,available_at=UTC_TIMESTAMP(6),lease_owner=NULL,lease_until=NULL,fencing_version=fencing_version+1,last_error='lease_expired',completed_at=IF(?='DEAD',UTC_TIMESTAMP(6),NULL) WHERE id=?",[$status,$status,(string)$row['id']]);
                $this->db->run("UPDATE job_attempts SET status='EXPIRED',ended_at=UTC_TIMESTAMP(6),safe_error='lease_expired' WHERE merchant_id=? AND job_id=? AND attempt_number=?",[(string)$row['merchant_id'],(string)$row['id'],(int)$row['attempt_count']]);
                (new AuditLog($this->db))->append($this->scope($row),Actor::system(),$dead?AuditAction::JobDead:AuditAction::JobRetried,bin2hex((string)$row['id']),new SafePayload(['attempt'=>(int)$row['attempt_count']]));++$count;
            }
            return $count;
        });
    }
    public function requeue(TenantContext $context,string $id): void
    {
        $context->require('operations.manage');
        $this->db->transaction(function()use($context,$id):void{
            (new AccessPolicy($this->db))->require($context,'operations.manage');
            $row=$this->db->one('SELECT * FROM jobs WHERE merchant_id=? AND id=? FOR UPDATE',[Id::bytes($context->merchantId),Id::bytes($id)]);
            if($row===null||$row['status']!=='DEAD'){throw new Conflict('job_not_dead');}
            if((int)$row['attempt_count']>=25){throw new Conflict('job_attempt_budget_exhausted');}
            $scope=$this->scope($row);(new ScopeGuard($this->db))->active($scope);
            if($scope->storeId===null && !(bool)$this->db->run('SELECT all_stores FROM memberships WHERE merchant_id=? AND id=?',[Id::bytes($context->merchantId),Id::bytes($context->membershipId)])->fetchColumn()){throw new \Ordely\Identity\Domain\AccessDenied('forbidden');}
            if($scope->storeId!==null && (new StoreRepository($this->db))->get($context,$scope->storeId)===null){throw new \Ordely\Identity\Domain\AccessDenied('forbidden');}
            $this->db->run("UPDATE jobs SET status='READY',max_attempts=LEAST(25,attempt_count+5),available_at=UTC_TIMESTAMP(6),completed_at=NULL,fencing_version=fencing_version+1 WHERE id=?",[Id::bytes($id)]);
            (new AuditLog($this->db))->append($scope,Actor::user($context->userId),AuditAction::JobRequeued,$id,new SafePayload());
        });
    }
    /** @param array<string,mixed> $row */
    private function scope(array $row): Scope { return new Scope(bin2hex((string)$row['merchant_id']),$row['store_id']===null?null:bin2hex((string)$row['store_id'])); }
    /** @return list<string|int|null> */
    private function leaseParameters(JobLease $lease): array { return [Id::bytes($lease->scope->merchantId),$lease->scope->storeId===null?null:Id::bytes($lease->scope->storeId),Id::bytes($lease->id),Id::bytes($lease->owner),$lease->fence]; }
    private function audit(JobLease $lease,AuditAction $action): void { (new AuditLog($this->db))->append($lease->scope,Actor::system(),$action,$lease->id,new SafePayload(['attempt'=>$lease->attempt])); }
    private function leaseDuration(int $seconds): void { if($seconds<1||$seconds>3600){throw new \InvalidArgumentException('Invalid lease duration.');} }
}
