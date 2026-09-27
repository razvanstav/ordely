<?php

declare(strict_types=1);

namespace Ordely\Infrastructure\Database;

use RuntimeException;

final readonly class Migrator
{
    public function __construct(private Sql $db, private string $directory) {}

    /** @return list<string> */
    public function migrate(): array
    {
        if ($this->db->pdo->inTransaction()) {
            throw new RuntimeException('Migrations cannot run inside a transaction.');
        }
        $lock = 'ordely:migrate:' . substr(hash('sha256', (string) $this->db->run('SELECT DATABASE()')->fetchColumn()), 0, 48);
        if ((int) $this->db->run('SELECT GET_LOCK(?, 30)', [$lock])->fetchColumn() !== 1) {
            throw new RuntimeException('Migration lock unavailable.');
        }
        try {
            $this->db->run("CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) PRIMARY KEY, checksum CHAR(64) NOT NULL, status VARCHAR(16) NOT NULL, applied_at DATETIME(6) NULL) ENGINE=InnoDB");
            $files = glob($this->directory . '/*.sql');
            if ($files === false || $files === []) { throw new RuntimeException('Migrations missing.'); }
            sort($files, SORT_STRING);
            $applied = [];
            foreach ($files as $file) {
                $source = file_get_contents($file);
                if ($source === false) { throw new RuntimeException('Cannot read migration.'); }
                // Normalize checkout line endings for the same checksum on Windows and Linux.
                $source = str_replace("\r\n", "\n", $source);
                $checksum = hash('sha256', $source);
                $name = basename($file);
                $existing = $this->db->one('SELECT checksum, status FROM schema_migrations WHERE name = ?', [$name]);
                if ($existing !== null) {
                    if ($existing['checksum'] !== $checksum || $existing['status'] !== 'applied') {
                        throw new RuntimeException('Changed or incomplete migration: ' . $name);
                    }
                    continue;
                }
                // MySQL DDL commits implicitly. Persist a dirty marker, never promise DDL rollback.
                $this->db->run("INSERT INTO schema_migrations (name, checksum, status) VALUES (?, ?, 'applying')", [$name, $checksum]);
                foreach (explode(';', $source) as $statement) {
                    if (trim($statement) !== '') { $this->db->run($statement); }
                }
                $this->db->run("UPDATE schema_migrations SET status = 'applied', applied_at = UTC_TIMESTAMP(6) WHERE name = ?", [$name]);
                $applied[] = $name;
            }
            return $applied;
        } finally {
            $this->db->run('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }
}
