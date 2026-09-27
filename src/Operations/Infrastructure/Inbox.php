<?php
declare(strict_types=1);
namespace Ordely\Operations\Infrastructure;
use Ordely\Core\Value\OperationKey;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\{Conflict,SafePayload,Scope};
use Ordely\Shared\Id;

/** Internal ingress AFTER signature/account validation. Raw webhook payload storage belongs to encrypted integration ingress. */
final readonly class Inbox
{
    public function __construct(private Sql $db) {}
    public function receive(Scope $scope,OperationKey $source,OperationKey $delivery,string $rawBodyHash,SafePayload $references,string $jobType): string
    {
        if($scope->storeId===null||!preg_match('/^[a-f0-9]{64}$/D',$rawBodyHash)){throw new \InvalidArgumentException('Inbox requires store and SHA-256 digest.');}
        (new ScopeGuard($this->db))->active($scope);
        return $this->db->transaction(function()use($scope,$source,$delivery,$rawBodyHash,$references,$jobType):string{
            $sourceHash=hash('sha256',$source->value,true);$deliveryHash=hash('sha256',$delivery->value,true);
            $hash=hash('sha256',$rawBodyHash.':'.$jobType.':'.$references->json(),true);
            $dedupe=new OperationKey('inbox:'.hash('sha256',$source->value.':'.$delivery->value));
            $job=(new MySqlJobQueue($this->db))->enqueue($scope,$jobType,$references,$dedupe);
            $id=Id::new();
            $this->db->run('INSERT INTO inbox_events(id,merchant_id,store_id,source_key,delivery_key,request_hash,payload_refs,job_id) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',
                [Id::bytes($id),Id::bytes($scope->merchantId),Id::bytes($scope->storeId ?? ''),$sourceHash,$deliveryHash,$hash,$references->json(),Id::bytes($job)]);
            $row=$this->db->one('SELECT id,request_hash FROM inbox_events WHERE merchant_id=? AND store_id=? AND source_key=? AND delivery_key=? FOR UPDATE',[Id::bytes($scope->merchantId),Id::bytes($scope->storeId ?? ''),$sourceHash,$deliveryHash]);
            if($row===null){throw new \LogicException('Inbox insert missing.');}
            if(!hash_equals((string)$row['request_hash'],$hash)){throw new Conflict('inbox_payload_mismatch');}
            return bin2hex((string)$row['id']);
        });
    }
}
