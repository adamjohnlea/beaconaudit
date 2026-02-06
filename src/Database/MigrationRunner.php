<?php

declare(strict_types=1);

namespace App\Database;

final class MigrationRunner
{
    public function __construct(
        private readonly Database $database,
    ) {
    }

    public function run(): void
    {
        $this->createMigrationsTable();

        $migrationsPath = __DIR__ . '/Migrations';

        /** @var array<string> $files */
        $files = glob($migrationsPath . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);

            if ($this->hasRun($filename)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }

            $this->database->exec($sql);
            $this->recordMigration($filename);
        }
    }

    private function createMigrationsTable(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL UNIQUE,
                ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );
    }

    private function hasRun(string $filename): bool
    {
        $stmt = $this->database->query(
            'SELECT COUNT(*) as count FROM migrations WHERE filename = ?',
            [$filename],
        );

        /** @var array{count: int} $row */
        $row = $stmt->fetch();

        return $row['count'] > 0;
    }

    private function recordMigration(string $filename): void
    {
        $this->database->query(
            'INSERT INTO migrations (filename) VALUES (?)',
            [$filename],
        );
    }
}
