<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;

use Ordely\Identity\Domain\{Role, TenantContext};
use Ordely\Infrastructure\Database\{ConnectionFactory, DatabaseConfig, Migrator, Sql};
use Ordely\Shared\Id;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected Sql $db;
    private static string $passwordHash;
    protected const PASSWORD = 'Ordely-test-password-2026';

    public static function setUpBeforeClass(): void
    {
        $db = self::database();
        (new Migrator($db, dirname(__DIR__, 2) . '/database/migrations'))->migrate();
        self::$passwordHash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
    }

    protected static function database(): Sql { return new Sql((new ConnectionFactory(DatabaseConfig::fromEnvironment(true)))->connect()); }

    protected function setUp(): void { $this->db = self::database(); $this->db->pdo->beginTransaction(); }
    protected function tearDown(): void { if ($this->db->pdo->inTransaction()) { $this->db->pdo->rollBack(); } }

    protected function tenant(Role $role = Role::Owner, bool $allStores = true): TenantContext
    {
        $merchant = Id::new(); $membership = Id::new(); $user = Id::new();
        $this->db->run('INSERT INTO merchants(id,name) VALUES (?,?)', [Id::bytes($merchant), 'Test merchant']);
        $this->db->run('INSERT INTO users(id,email,password_hash) VALUES (?,?,?)', [Id::bytes($user), $user . '@example.test', self::$passwordHash]);
        $this->db->run('INSERT INTO memberships(id,merchant_id,user_id,role,all_stores) VALUES (?,?,?,?,?)', [Id::bytes($membership), Id::bytes($merchant), Id::bytes($user), $role->value, (int) $allStores]);
        return new TenantContext($merchant, $membership, $user, $role, $allStores);
    }

    protected function store(TenantContext $context, string $name = 'Test store'): string
    {
        $id = Id::new();
        $this->db->run("INSERT INTO stores(id,merchant_id,name,platform_key) VALUES (?,?,?,'manual')", [Id::bytes($id), Id::bytes($context->merchantId), $name]);
        return $id;
    }
}
