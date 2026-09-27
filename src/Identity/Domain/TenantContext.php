<?php
declare(strict_types=1);
namespace Ordely\Identity\Domain;

use Ordely\Shared\Id;

final readonly class TenantContext
{
    public function __construct(public string $merchantId, public string $membershipId, public string $userId, public Role $role, public bool $allStores)
    {
        Id::bytes($merchantId); Id::bytes($membershipId); Id::bytes($userId);
    }

    public function require(string $permission): void
    {
        if (!$this->role->allows($permission)) { throw new AccessDenied('forbidden'); }
    }
}
