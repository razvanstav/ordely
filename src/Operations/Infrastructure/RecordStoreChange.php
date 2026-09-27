<?php
declare(strict_types=1);
namespace Ordely\Operations\Infrastructure;
use Ordely\Identity\Domain\TenantContext;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\{Actor,AuditAction,EventType,SafePayload,Scope};
use Ordely\Shared\Id;
final readonly class RecordStoreChange
{
    public function __construct(private Sql $db) {}
    public function record(TenantContext $context,string $store,int $version,bool $created): void
    {
        $scope=new Scope($context->merchantId,$store);$correlation=Id::new();$data=new SafePayload(['store_id'=>$store,'version'=>$version]);
        (new AuditLog($this->db))->append($scope,Actor::user($context->userId),$created?AuditAction::StoreCreated:AuditAction::StoreRenamed,$store,$data,$correlation);
        (new Outbox($this->db))->append($scope,$created?EventType::StoreCreated:EventType::StoreRenamed,$store,$version,$data,$correlation);
    }
}
