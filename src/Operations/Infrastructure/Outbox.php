<?php
declare(strict_types=1);
namespace Ordely\Operations\Infrastructure;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\{EventType,SafePayload,Scope};
use Ordely\Shared\Id;
final readonly class Outbox
{
    public function __construct(private Sql $db) {}
    public function append(Scope $scope,EventType $type,string $aggregateId,int $version,SafePayload $payload,?string $correlation=null): string
    {
        if(!$this->db->pdo->inTransaction() || $version<1){throw new \LogicException('Outbox requires a business transaction and positive version.');}
        (new ScopeGuard($this->db))->active($scope);
        $id=Id::new();
        $this->db->run('INSERT INTO outbox_events(id,merchant_id,store_id,event_type,schema_version,aggregate_id,aggregate_version,correlation_id,payload) VALUES(?,?,?,?,1,?,?,?,?)',
            [Id::bytes($id),Id::bytes($scope->merchantId),$scope->storeId===null?null:Id::bytes($scope->storeId),$type->value,Id::bytes($aggregateId),$version,Id::bytes($correlation ?? Id::new()),$payload->json()]);
        return $id;
    }
}
