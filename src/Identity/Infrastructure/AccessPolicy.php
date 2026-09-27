<?php
declare(strict_types=1);
namespace Ordely\Identity\Infrastructure;
use Ordely\Identity\Domain\{AccessDenied,Role,TenantContext};
use Ordely\Infrastructure\Database\Sql;
use Ordely\Shared\Id;

final readonly class AccessPolicy
{
    public function __construct(private Sql $db) {}
    public function require(TenantContext $context,string $permission,?string $storeId=null): void
    {
        $row=$this->db->one("SELECT m.role FROM memberships m JOIN merchants t ON t.id=m.merchant_id JOIN users u ON u.id=m.user_id
            WHERE m.merchant_id=? AND m.id=? AND m.user_id=? AND m.status='active' AND t.status='active' AND u.status='active'".($this->db->pdo->inTransaction()?' FOR SHARE':''),[Id::bytes($context->merchantId),Id::bytes($context->membershipId),Id::bytes($context->userId)]);
        if($row===null || !Role::from((string)$row['role'])->allows($permission)){throw new AccessDenied('forbidden');}
        if($storeId!==null && (new StoreRepository($this->db))->get($context,$storeId)===null){throw new AccessDenied('forbidden');}
    }
}
