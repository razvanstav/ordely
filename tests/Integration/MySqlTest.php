<?php

declare(strict_types=1);

namespace Ordely\Tests\Integration;

use Ordely\Infrastructure\Database\ConnectionFactory;
use Ordely\Infrastructure\Database\DatabaseConfig;
use Ordely\Infrastructure\Http\Application;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class MySqlTest extends TestCase
{
    private function connect(): PDO
    {
        return (new ConnectionFactory(DatabaseConfig::fromEnvironment(testDatabase: true)))->connect();
    }

    private function scalar(PDO $connection, string $sql): mixed
    {
        $statement = $connection->query($sql);
        if ($statement === false) {
            self::fail('The MySQL query must return a statement.');
        }

        return $statement->fetchColumn();
    }

    public function testServerAndSessionMatchTheRequiredRuntime(): void
    {
        $connection = $this->connect();
        self::assertStringStartsWith('8.4.', (string) $this->scalar($connection, 'SELECT VERSION()'));
        self::assertSame('+00:00', $this->scalar($connection, 'SELECT @@session.time_zone'));
        self::assertSame('utf8mb4', $this->scalar($connection, 'SELECT @@character_set_connection'));
        self::assertStringContainsString('STRICT_TRANS_TABLES', (string) $this->scalar($connection, 'SELECT @@session.sql_mode'));
        self::assertFalse((bool) $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
    }

    public function testPreparedUnicodeWritesRollBackInInnoDb(): void
    {
        $connection = $this->connect();
        $connection->exec('CREATE TEMPORARY TABLE ordely_smoke (id INT PRIMARY KEY, value VARCHAR(255)) ENGINE=InnoDB');
        $connection->beginTransaction();
        try {
            $value = "Retur mărime 🧵 ' OR 1=1 --";
            $statement = $connection->prepare('INSERT INTO ordely_smoke (id, value) VALUES (?, ?)');
            $statement->execute([1, $value]);
            self::assertSame($value, $this->scalar($connection, 'SELECT value FROM ordely_smoke WHERE id = 1'));
        } finally {
            $connection->rollBack();
        }

        self::assertSame(0, (int) $this->scalar($connection, 'SELECT COUNT(*) FROM ordely_smoke'));
    }

    public function testReadinessWithAnActualDatabase(): void
    {
        $application = new Application(fn (): PDO => $this->connect());
        $response = $application->handle(Request::create('/ready'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"status":"ready"}', $response->getContent());
    }
}
