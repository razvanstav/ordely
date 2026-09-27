<?php

declare(strict_types=1);

namespace Ordely\Infrastructure\Configuration;

use InvalidArgumentException;

final class Environment
{
    public static function string(string $name, ?string $default = null): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        if ($value === false) {
            $value = $default;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Missing or invalid environment variable: ' . $name);
        }

        return $value;
    }
}
