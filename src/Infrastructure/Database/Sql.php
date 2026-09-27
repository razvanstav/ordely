<?php

declare(strict_types=1);

namespace Ordely\Infrastructure\Database;

use PDO;
use PDOStatement;

final readonly class Sql
{
    public function __construct(public PDO $pdo) {}

    /** @param list<string|int|null> $parameters */
    public function run(string $query, array $parameters = []): PDOStatement
    {
        $statement = $this->pdo->prepare($query);
        $statement->execute($parameters);
        return $statement;
    }

    /** @param list<string|int|null> $parameters
     * @return array<string, mixed>|null */
    public function one(string $query, array $parameters = []): ?array
    {
        $row = $this->run($query, $parameters)->fetch();
        return is_array($row) ? $row : null;
    }

    /** @template T
     * @param callable(): T $work
     * @return T */
    public function transaction(callable $work): mixed
    {
        $nested = $this->pdo->inTransaction();
        $savepoint = 'sp_' . bin2hex(random_bytes(8));
        $nested ? $this->pdo->exec('SAVEPOINT ' . $savepoint) : $this->pdo->beginTransaction();
        try {
            $result = $work();
            $nested ? $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint) : $this->pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $nested ? $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint) : $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
