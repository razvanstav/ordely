<?php
declare(strict_types=1);
namespace Ordely\Operations\Infrastructure;
use Ordely\Core\Contracts\{ConnectionContext,ErrorCategory,ProviderFailure};
use Ordely\Core\Value\{CanonicalJson,ExternalId,OperationKey};
use Ordely\Identity\Domain\{AccessDenied,TenantContext};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,ExternalLease,ExternalState,LeaseLost,OperationResult,SafePayload,Scope};
use Ordely\Shared\Id;

final readonly class ExternalOperations
{
    /** @param (\Closure(ConnectionContext):void)|null $connectionCheck
     * @param (\Closure(ConnectionContext):void)|null $reservationCheck Database-only guard inside reservation transaction.
     * @param (\Closure(ConnectionContext,ExternalLease,ExternalId):void)|null $onConfirmed Database-only result persistence inside confirmation transaction. */
    public function __construct(private Sql $db,private ?\Closure $connectionCheck=null,private ?\Closure $reservationCheck=null,private ?\Closure $onConfirmed=null) {}

    /** @param \Closure(OperationKey):ExternalId $call */
    public function execute(ConnectionContext $context,string $type,OperationKey $businessKey,#[\SensitiveParameter] mixed $request,\Closure $call,int $leaseSeconds=60): OperationResult
    {
        if($this->db->pdo->inTransaction()){throw new \LogicException('External calls cannot run inside a database transaction.');}
        if(!preg_match('/^[a-z][a-z0-9_.]{0,63}$/D',$type)||$leaseSeconds<1||$leaseSeconds>3600){throw new \InvalidArgumentException('Invalid external operation.');}
        $scope=Scope::connection($context);(new ScopeGuard($this->db))->active($scope);
        (new \Ordely\Integrations\Infrastructure\ConnectionGuard($this->db))->active($context);
        if($this->connectionCheck!==null){($this->connectionCheck)($context);}
        $hash=hash('sha256',CanonicalJson::encode([$context->connection,$type,$request]),true);
        $reservation=$this->reserve($context,$type,$businessKey,$hash,$leaseSeconds);
        if($reservation instanceof OperationResult){return $reservation;}
        $sent=false;$reference=null;$failure=null;$retryAfter=null;
        try{
            (new ScopeGuard($this->db))->active($scope);if($this->connectionCheck!==null){($this->connectionCheck)($context);}(new \Ordely\Integrations\Infrastructure\ConnectionGuard($this->db))->active($context);
            $sent=true;$reference=$call($reservation->providerKey);
        }catch(\Throwable $error){
            $failure=match(true){$error instanceof ProviderFailure=>$error->category,$error instanceof AccessDenied&&!$sent=>ErrorCategory::Authentication,!$sent=>ErrorCategory::Transient,default=>ErrorCategory::Unknown};
            $retryAfter=$error instanceof ProviderFailure?$error->retryAfterSeconds:null;
        }
        return $this->finish($context,$reservation,$reference,$failure,$retryAfter);
    }
    private function reserve(ConnectionContext $context,string $type,OperationKey $business,string $hash,int $seconds): ExternalLease|OperationResult
    {
        return $this->db->transaction(function()use($context,$type,$business,$hash,$seconds):ExternalLease|OperationResult{
            if($this->reservationCheck!==null){($this->reservationCheck)($context);}
            $scope=Scope::connection($context);$id=Id::new();$providerKey='operation:'.$id;$businessHash=hash('sha256',$business->value,true);
            $params=[Id::bytes($scope->merchantId),Id::bytes($context->store->value),$type,$businessHash];
            $this->db->run('INSERT INTO external_operations(id,merchant_id,store_id,connection_id,type,business_key,request_hash,provider_key,correlation_id) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',
                [Id::bytes($id),$params[0],$params[1],Id::bytes($context->connection->value),$type,$businessHash,$hash,$providerKey,Id::bytes($context->correlation->value)]);
            $row=$this->db->one('SELECT *,lease_until>UTC_TIMESTAMP(6) live,next_attempt_at>UTC_TIMESTAMP(6) waiting,TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(6),next_attempt_at) retry_after FROM external_operations WHERE merchant_id=? AND store_id=? AND type=? AND business_key=? FOR UPDATE',$params);
            if($row===null){throw new \LogicException('External intent missing.');}
            if(!hash_equals((string)$row['request_hash'],$hash)){throw new Conflict('business_intent_mismatch');}
            $id=bin2hex((string)$row['id']);$state=ExternalState::from((string)$row['status']);
            if(in_array($state,[ExternalState::Confirmed,ExternalState::Unknown,ExternalState::Failed],true)){return $this->result($row);}
            if($state===ExternalState::Retryable&&(bool)$row['waiting']){return new OperationResult($id,$state,null,(int)$row['fencing_version'],max(1,(int)$row['retry_after']));}
            if($state===ExternalState::InFlight){
                if((bool)$row['live']){return $this->result($row);}
                $this->db->run("UPDATE external_operations SET status='UNKNOWN',safe_error='unknown',lease_owner=NULL,lease_until=NULL,fencing_version=fencing_version+1 WHERE id=?",[(string)$row['id']]);
                $this->db->run("UPDATE external_attempts SET status='UNKNOWN',safe_error='unknown',ended_at=UTC_TIMESTAMP(6) WHERE merchant_id=? AND operation_id=? AND attempt_number=?",[$params[0],(string)$row['id'],(int)$row['attempt_count']]);
                $this->audit($context,$id,AuditAction::ExternalUnknown,(int)$row['attempt_count']);
                return new OperationResult($id,ExternalState::Unknown,null,(int)$row['fencing_version']+1);
            }
            $owner=Id::new();$fence=(int)$row['fencing_version']+1;$attempt=(int)$row['attempt_count']+1;
            $this->db->run("UPDATE external_operations SET status='IN_FLIGHT',lease_owner=?,lease_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL ? SECOND),fencing_version=?,attempt_count=?,safe_error=NULL,next_attempt_at=NULL WHERE id=?",[Id::bytes($owner),$seconds,$fence,$attempt,(string)$row['id']]);
            $this->db->run("INSERT INTO external_attempts(merchant_id,operation_id,attempt_number,status) VALUES(?,?,?,'IN_FLIGHT')",[$params[0],(string)$row['id'],$attempt]);
            $this->audit($context,$id,AuditAction::ExternalStarted,$attempt);
            return new ExternalLease($id,$owner,$fence,$attempt,new OperationKey((string)$row['provider_key']));
        });
    }
    private function finish(ConnectionContext $context,ExternalLease $lease,?ExternalId $reference,?ErrorCategory $error,?int $retryAfter): OperationResult
    {
        return $this->db->transaction(function()use($context,$lease,$reference,$error,$retryAfter):OperationResult{
            $state=$error===null?ExternalState::Confirmed:match($error){ErrorCategory::Transient=>$lease->attempt<5?ExternalState::Retryable:ExternalState::Failed,ErrorCategory::Unknown=>ExternalState::Unknown,default=>ExternalState::Failed};
            $delay=$state===ExternalState::Retryable?max($retryAfter ?? 0,5*(2**min(10,$lease->attempt-1))+random_int(0,5)):0;
            $changed=$this->db->run("UPDATE external_operations SET status=?,provider_reference=?,safe_error=?,lease_owner=NULL,lease_until=NULL,next_attempt_at=IF(?='RETRYABLE',DATE_ADD(UTC_TIMESTAMP(6),INTERVAL ? SECOND),NULL) WHERE merchant_id=? AND store_id=? AND id=? AND status='IN_FLIGHT' AND lease_owner=? AND fencing_version=?",
                [$state->value,$reference?->value,$error?->value,$state->value,$delay,Id::bytes($context->merchant->value),Id::bytes($context->store->value),Id::bytes($lease->id),Id::bytes($lease->owner),$lease->fence])->rowCount();
            if($changed!==1){throw new LeaseLost('external_lease_lost');}
            if($state===ExternalState::Confirmed&&$reference!==null&&$this->onConfirmed!==null){($this->onConfirmed)($context,$lease,$reference);}
            $this->db->run('UPDATE external_attempts SET status=?,safe_error=?,ended_at=UTC_TIMESTAMP(6) WHERE merchant_id=? AND operation_id=? AND attempt_number=?',[$state->value,$error?->value,Id::bytes($context->merchant->value),Id::bytes($lease->id),$lease->attempt]);
            $this->audit($context,$lease->id,match($state){ExternalState::Confirmed=>AuditAction::ExternalConfirmed,ExternalState::Unknown=>AuditAction::ExternalUnknown,default=>AuditAction::ExternalFailed},$lease->attempt);
            return new OperationResult($lease->id,$state,$reference,$lease->fence,$delay>0?$delay:null);
        });
    }
    public function confirmReconciled(TenantContext $actor,string $id,int $expectedVersion,ExternalId $reference,string $evidenceHash): OperationResult
    {
        $evidence=new SafePayload(['operation_id'=>$id,'evidence_sha256'=>$evidenceHash]);
        return $this->db->transaction(function()use($actor,$id,$expectedVersion,$reference,$evidence):OperationResult{
            $row=$this->db->one('SELECT * FROM external_operations WHERE merchant_id=? AND id=? FOR UPDATE',[Id::bytes($actor->merchantId),Id::bytes($id)]);
            if($row===null){throw new AccessDenied('forbidden');}
            $store=bin2hex((string)$row['store_id']);(new AccessPolicy($this->db))->require($actor,'operations.manage',$store);
            if($row['status']!=='UNKNOWN'||(int)$row['fencing_version']!==$expectedVersion){throw new Conflict('reconciliation_version_mismatch');}
            $this->db->run("UPDATE external_operations SET status='CONFIRMED',provider_reference=?,safe_error=NULL,fencing_version=fencing_version+1 WHERE id=?",[$reference->value,Id::bytes($id)]);
            (new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),AuditAction::ExternalReconciled,$id,$evidence,bin2hex((string)$row['correlation_id']));
            return new OperationResult($id,ExternalState::Confirmed,$reference,$expectedVersion+1);
        });
    }
    /** @param array<string,mixed> $row */
    private function result(array $row): OperationResult { return new OperationResult(bin2hex((string)$row['id']),ExternalState::from((string)$row['status']),$row['provider_reference']===null?null:new ExternalId((string)$row['provider_reference']),(int)$row['fencing_version']); }
    private function audit(ConnectionContext $context,string $id,AuditAction $action,int $attempt): void { (new AuditLog($this->db))->append(Scope::connection($context),Actor::system(),$action,$id,new SafePayload(['operation_id'=>$id,'attempt'=>$attempt]),$context->correlation->value); }
}
