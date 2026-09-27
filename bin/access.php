<?php
declare(strict_types=1);

use Ordely\Identity\Application\Passwords;
use Ordely\Identity\Domain\Role;
use Ordely\Identity\Infrastructure\Provisioner;
use Ordely\Infrastructure\Configuration\Environment;
use Ordely\Infrastructure\Database\{ConnectionFactory, DatabaseConfig, Sql};
use Ordely\Shared\Id;

require dirname(__DIR__) . '/config/bootstrap.php';
try {
    $db = new Sql((new ConnectionFactory(DatabaseConfig::fromEnvironment()))->connect());
    $command = $argv[1] ?? '';
    $db->transaction(function () use ($db, $command): void {
        $provisioner = new Provisioner($db);
        switch ($command) {
            case 'create-user':
                $id = Id::new();
                $db->run('INSERT INTO users(id,email,password_hash) VALUES (?,?,?)', [Id::bytes($id), Passwords::email(Environment::string('ORDELY_EMAIL')), Passwords::hash(Environment::string('ORDELY_PASSWORD'))]);
                echo 'User: ' . $id . PHP_EOL;
                break;
            case 'add-member':
                $id = $provisioner->addMembership(Environment::string('ORDELY_MERCHANT_ID'), Environment::string('ORDELY_USER_ID'), Role::from(Environment::string('ORDELY_ROLE')), Environment::string('ORDELY_ALL_STORES', '0') === '1');
                echo 'Membership: ' . $id . PHP_EOL;
                break;
            case 'grant-store':
                $provisioner->grantStore(Environment::string('ORDELY_MERCHANT_ID'), Environment::string('ORDELY_MEMBERSHIP_ID'), Environment::string('ORDELY_STORE_ID'));
                break;
            case 'revoke-store':
                $db->run('DELETE FROM membership_store_grants WHERE merchant_id=? AND membership_id=? AND store_id=?', [Id::bytes(Environment::string('ORDELY_MERCHANT_ID')), Id::bytes(Environment::string('ORDELY_MEMBERSHIP_ID')), Id::bytes(Environment::string('ORDELY_STORE_ID'))]);
                break;
            case 'disable-member':
                $db->run("UPDATE memberships SET status='disabled' WHERE merchant_id=? AND id=?", [Id::bytes(Environment::string('ORDELY_MERCHANT_ID')), Id::bytes(Environment::string('ORDELY_MEMBERSHIP_ID'))]);
                break;
            default: throw new InvalidArgumentException('Unknown access command.');
        }
    });
    echo 'Access command completed.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Access command failed (' . $error::class . '). Check docs/identity.md and environment inputs.' . PHP_EOL);
    exit(1);
}
