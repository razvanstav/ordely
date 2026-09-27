<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;

use Ordely\Infrastructure\Database\{ConnectionFactory, DatabaseConfig, Migrator, Sql};
use Ordely\Shared\Id;
use PHPUnit\Framework\TestCase;

final class MigrationsTest extends TestCase
{
    public function testRepeatedMigrationsAreNoOps(): void
    {
        $db = new Sql((new ConnectionFactory(DatabaseConfig::fromEnvironment(true)))->connect());
        $runner = new Migrator($db, dirname(__DIR__, 2) . '/database/migrations');
        $runner->migrate();
        self::assertSame([], $runner->migrate());
    }

    public function testChangedAndPartiallyFailedMigrationsAreRejected(): void
    {
        $db = new Sql((new ConnectionFactory(DatabaseConfig::fromEnvironment(true)))->connect());
        $name = 'test_' . Id::new();
        $directory = dirname(__DIR__, 2) . '/var/' . $name;
        mkdir($directory, 0700, true);
        $file = $directory . '/' . $name . '.sql';
        try {
            file_put_contents($file, "SELECT 1;\n");
            $runner = new Migrator($db, $directory);
            self::assertSame([$name . '.sql'], $runner->migrate());
            file_put_contents($file, "SELECT 2;\n");
            try { $runner->migrate(); self::fail('Expected checksum mismatch.'); }
            catch (\RuntimeException $error) { self::assertStringContainsString('Changed or incomplete', $error->getMessage()); }
            $db->run('DELETE FROM schema_migrations WHERE name=?', [$name . '.sql']);
            file_put_contents($file, "SELECT 1;\nINVALID SQL;\n");
            try { $runner->migrate(); self::fail('Expected SQL failure.'); }
            catch (\PDOException) { self::assertSame('applying', $db->run('SELECT status FROM schema_migrations WHERE name=?', [$name . '.sql'])->fetchColumn()); }
            try { $runner->migrate(); self::fail('Expected dirty migration rejection.'); }
            catch (\RuntimeException $error) { self::assertStringContainsString('Changed or incomplete', $error->getMessage()); }
        } finally {
            $db->run('DELETE FROM schema_migrations WHERE name=?', [$name . '.sql']);
            if (is_file($file)) { unlink($file); }
            rmdir($directory);
        }
    }
}
