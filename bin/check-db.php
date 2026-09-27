<?php

declare(strict_types=1);

use Ordely\Infrastructure\Database\ConnectionFactory;
use Ordely\Infrastructure\Database\DatabaseConfig;

require dirname(__DIR__) . '/config/bootstrap.php';

try {
    $connection = (new ConnectionFactory(DatabaseConfig::fromEnvironment()))->connect();
    $version = (string) $connection->getAttribute(PDO::ATTR_SERVER_VERSION);
    fwrite(STDOUT, 'MySQL connection ready: ' . $version . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Database check failed (' . $exception::class . '). Check your local DB configuration and MySQL 8.4 process.' . PHP_EOL);
    exit(1);
}
