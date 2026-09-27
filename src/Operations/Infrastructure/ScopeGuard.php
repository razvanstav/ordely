<?php
declare(strict_types=1);
namespace Ordely\Operations\Infrastructure;
use Ordely\Identity\Domain\AccessDenied;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\Scope;
use Ordely\Shared\Id;
final readonly class ScopeGuard
{
    public function __construct(private Sql $db) {}
    public function active(Scope $scope): void
    {
        $merchant=$this->db->one("SELECT id FROM merchants WHERE id=? AND status='active'",[Id::bytes($scope->merchantId)]);
        if($merchant===null){throw new AccessDenied('scope_inactive');}
        if($scope->storeId!==null && $this->db->one("SELECT id FROM stores WHERE merchant_id=? AND id=? AND status='active'",[Id::bytes($scope->merchantId),Id::bytes($scope->storeId)])===null){throw new AccessDenied('scope_inactive');}
    }
}
