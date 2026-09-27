<?php

declare(strict_types=1);

namespace Ordely\Infrastructure\Database;

use InvalidArgumentException;
use Ordely\Infrastructure\Configuration\Environment;
use SensitiveParameter;

final readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        #[SensitiveParameter] public string $password,
    ) {
        if (!preg_match('/\A[a-zA-Z0-9._-]+\z/', $host)) {
            throw new InvalidArgumentException('DB_HOST must be a hostname or IPv4 address.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('DB_PORT must be between 1 and 65535.');
        }
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $database)) {
            throw new InvalidArgumentException('DB_NAME must contain only letters, digits and underscores.');
        }
        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('Database credentials must be configured.');
        }
    }

    public static function fromEnvironment(bool $testDatabase = false): self
    {
        $port = Environment::string('DB_PORT', '3306');
        if (!ctype_digit($port)) {
            throw new InvalidArgumentException('DB_PORT must be an integer.');
        }
        $database = Environment::string($testDatabase ? 'DB_TEST_NAME' : 'DB_NAME');
        if ($testDatabase && !str_ends_with($database, '_test')) {
            throw new InvalidArgumentException('Integration tests require a database name ending in _test.');
        }

        return new self(
            Environment::string('DB_HOST', '127.0.0.1'),
            (int) $port,
            $database,
            Environment::string('DB_USER'),
            Environment::string('DB_PASSWORD'),
        );
    }

    public function dsn(): string
    {
        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->host, $this->port, $this->database);
    }
}
