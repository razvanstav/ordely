<?php

declare(strict_types=1);

namespace Ordely\Tests\Unit;

use InvalidArgumentException;
use Ordely\Infrastructure\Database\DatabaseConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    /** @return iterable<string, array{string, int, string, string, string}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'host DSN injection' => ['localhost;dbname=other', 3306, 'ordely', 'user', 'test-only'];
        yield 'database DSN injection' => ['localhost', 3306, 'ordely;charset=latin1', 'user', 'test-only'];
        yield 'port zero' => ['localhost', 0, 'ordely', 'user', 'test-only'];
        yield 'port overflow' => ['localhost', 65536, 'ordely', 'user', 'test-only'];
        yield 'empty username' => ['localhost', 3306, 'ordely', '', 'test-only'];
        yield 'empty password' => ['localhost', 3306, 'ordely', 'user', ''];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationIsRejected(string $host, int $port, string $database, string $username, string $password): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DatabaseConfig($host, $port, $database, $username, $password);
    }

    public function testCredentialsAreNotEmbeddedInDsn(): void
    {
        $config = new DatabaseConfig('127.0.0.1', 33060, 'ordely', 'user', 'test-only');

        self::assertSame('mysql:host=127.0.0.1;port=33060;dbname=ordely;charset=utf8mb4', $config->dsn());
        self::assertStringNotContainsString('test-only', $config->dsn());
    }

    public function testIntegrationConfigurationRejectsAnApplicationDatabase(): void
    {
        $previous = $_ENV;
        try {
            $_ENV['DB_PORT'] = '3306';
            $_ENV['DB_TEST_NAME'] = 'ordely';
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('ending in _test');
            DatabaseConfig::fromEnvironment(testDatabase: true);
        } finally {
            $_ENV = $previous;
        }
    }

    public function testEnvironmentPortRejectsPartiallyNumericInput(): void
    {
        $previous = $_ENV;
        try {
            $_ENV['DB_PORT'] = '3306-invalid';
            $this->expectException(InvalidArgumentException::class);
            DatabaseConfig::fromEnvironment();
        } finally {
            $_ENV = $previous;
        }
    }
}
