<?php
declare(strict_types=1);
namespace Ordely\Operations\Infrastructure;
use Ordely\Core\Value\{CanonicalJson,OperationKey};
use Ordely\Identity\Domain\TenantContext;
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\{Conflict,SafePayload};
use Ordely\Shared\Id;

/** Local DB effects only: never hold this transaction during an external request. */
final readonly class IdempotencyGate
{
    public function __construct(private Sql $db) {}
    /** @param \Closure():SafePayload $work */
    public function run(TenantContext $actor,?string $storeId,string $action,string $permission,OperationKey $key,mixed $request,\Closure $work): SafePayload
    {
        $scope=hash('sha256',($storeId ?? 'merchant').':'.$actor->userId.':'.$action,true);$keyHash=hash('sha256',$key->value,true);$hash=hash('sha256',CanonicalJson::encode($request),true);
        return $this->db->transaction(function()use($actor,$storeId,$permission,$scope,$keyHash,$hash,$work):SafePayload{
            (new AccessPolicy($this->db))->require($actor,$permission,$storeId);
            $params=[Id::bytes($actor->merchantId),$scope,$keyHash];
            $this->db->run('INSERT INTO idempotency_requests(merchant_id,scope_hash,key_hash,request_hash) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE scope_hash=scope_hash',[...$params,$hash]);
            $row=$this->db->one('SELECT request_hash,result_refs FROM idempotency_requests WHERE merchant_id=? AND scope_hash=? AND key_hash=? FOR UPDATE',$params);
            if($row===null){throw new \LogicException('Idempotency row missing.');}
            if(!hash_equals((string)$row['request_hash'],$hash)){throw new Conflict('idempotency_payload_mismatch');}
            if($row['result_refs']!==null){return SafePayload::fromJson((string)$row['result_refs']);}
            $result=$work();
            $this->db->run('UPDATE idempotency_requests SET result_refs=? WHERE merchant_id=? AND scope_hash=? AND key_hash=?',[$result->json(),...$params]);
            return $result;
        });
    }
}
