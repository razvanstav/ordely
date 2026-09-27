<?php
declare(strict_types=1);

use Ordely\Identity\Infrastructure\Provisioner;
use Ordely\Infrastructure\Configuration\Environment;
use Ordely\Infrastructure\Database\{ConnectionFactory, DatabaseConfig, Sql};

require dirname(__DIR__) . '/config/bootstrap.php';
try {
    $result = (new Provisioner(new Sql((new ConnectionFactory(DatabaseConfig::fromEnvironment()))->connect())))
        ->create(Environment::string('ORDELY_MERCHANT'), Environment::string('ORDELY_EMAIL'), Environment::string('ORDELY_PASSWORD'));
    echo 'Created merchant: ' . $result['merchantId'] . PHP_EOL;
    echo 'User: ' . $result['userId'] . PHP_EOL;
    echo 'Membership: ' . $result['membershipId'] . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Provisioning failed (' . $error::class . '). Check environment inputs and unique email; no changes were committed.' . PHP_EOL);
    exit(1);
}
