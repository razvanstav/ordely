<?php

declare(strict_types=1);

namespace Ordely\Infrastructure\Database;

use PDO;
use RuntimeException;

final readonly class ConnectionFactory
{
    public function __construct(private DatabaseConfig $config)
    {
    }

    public function connect(): PDO
    {
        $connection = new PDO($this->config->dsn(), $this->config->username, $this->config->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3,
        ]);

        $version = (string) $connection->getAttribute(PDO::ATTR_SERVER_VERSION);
        if (!str_starts_with($version, '8.4.') || str_contains($version, 'MariaDB')) {
            throw new RuntimeException('This release requires MySQL 8.4.');
        }

        $connection->exec("SET SESSION time_zone = '+00:00'");
        $connection->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        return $connection;
    }
}
