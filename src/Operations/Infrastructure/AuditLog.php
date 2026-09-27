<?php
declare(strict_types=1);
namespace Ordely\Operations\Infrastructure;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\{Actor,AuditAction,SafePayload,Scope};
use Ordely\Shared\Id;
/** Append-only API. Database administrators still require separate operational controls. */
final readonly class AuditLog
{
    public function __construct(private Sql $db) {}
    public function append(Scope $scope,Actor $actor,AuditAction $action,string $entityId,SafePayload $data,?string $correlation=null): string
    {
        $id=Id::new();
        $this->db->run('INSERT INTO audit_logs(id,merchant_id,store_id,actor_type,actor_id,action,entity_id,correlation_id,safe_data) VALUES(?,?,?,?,?,?,?,?,?)',
            [Id::bytes($id),Id::bytes($scope->merchantId),$scope->storeId===null?null:Id::bytes($scope->storeId),$actor->type(),$actor->userId===null?null:Id::bytes($actor->userId),$action->value,Id::bytes($entityId),Id::bytes($correlation ?? Id::new()),$data->json()]);
        return $id;
    }
}
