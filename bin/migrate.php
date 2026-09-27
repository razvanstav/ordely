<?php

declare(strict_types=1);

use Ordely\Infrastructure\Database\{ConnectionFactory, DatabaseConfig, Migrator, Sql};

require dirname(__DIR__) . '/config/bootstrap.php';
try {
    $db = new Sql((new ConnectionFactory(DatabaseConfig::fromEnvironment(in_array('--test', $argv, true))))->connect());
    $applied = (new Migrator($db, dirname(__DIR__) . '/database/migrations'))->migrate();
    echo 'Migrations OK: ' . ($applied === [] ? 'already current' : implode(', ', $applied)) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration failed (' . $error::class . '). Inspect schema_migrations; never remove an incomplete marker without repairing the schema.' . PHP_EOL);
    exit(1);
}
