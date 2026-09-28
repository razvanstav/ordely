<?php
declare(strict_types=1);
namespace Ordely\Identity\Domain;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Operator = 'operator';
    case Finance = 'finance';
    case Viewer = 'viewer';

    public function allows(string $permission): bool
    {
        return match ($permission) {
            'orders.read' => $this !== self::Viewer,
            'stores.read' => true,
            'stores.manage', 'connections.manage', 'members.manage', 'operations.manage' => $this === self::Owner || $this === self::Admin,
            default => false,
        };
    }
}
