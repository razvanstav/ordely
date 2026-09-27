<?php

declare(strict_types=1);

namespace Ordely\Shared;

use InvalidArgumentException;

final class Id
{
    public static function new(): string { return bin2hex(random_bytes(16)); }

    public static function bytes(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new InvalidArgumentException('Invalid identifier.');
        }
        return pack('H*', $id);
    }
}
