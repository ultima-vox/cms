<?php

declare(strict_types=1);

namespace Core;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    public function __construct(
        private readonly PDO $db,
        private readonly string $migrationsPath,
    ) {
    }

    /** @return list<string> */
    public function migrate(): array
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (' .
            'migration VARCHAR(255) PRIMARY KEY, ' .
            'applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP)'
        );

        $this->db->query("SELECT pg_advisory_lock(hashtext('ultima_vox_cms_migrations'))");

        try {
            $files = glob($this->migrationsPath . '/*.sql');

            if ($files === false) {
                throw new RuntimeException('Не удалось прочитать каталог миграций.');
            }

            sort($files, SORT_STRING);
            $applied = [];

            foreach ($files as $file) {
                $name = basename($file);

                if ($this->isApplied($name)) {
                    continue;
                }

                $sql = file_get_contents($file);

                if ($sql === false) {
                    throw new RuntimeException(sprintf('Не удалось прочитать миграцию %s.', $name));
                }

                $this->applyMigration($name, $sql);
                $applied[] = $name;
            }

            return $applied;
        } finally {
            $this->db->query("SELECT pg_advisory_unlock(hashtext('ultima_vox_cms_migrations'))");
        }
    }

    private function applyMigration(string $name, string $sql): void
    {
        $this->db->beginTransaction();

        try {
            $this->db->exec($sql);

            $statement = $this->db->prepare(
                'INSERT INTO schema_migrations (migration) VALUES (:migration)'
            );
            $statement->execute(['migration' => $name]);

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function isApplied(string $migration): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM schema_migrations WHERE migration = :migration LIMIT 1'
        );
        $statement->execute(['migration' => $migration]);

        return $statement->fetchColumn() !== false;
    }
}
