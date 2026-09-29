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
        $this->ensureTrackingSchema();
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
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException(sprintf('Не удалось прочитать миграцию %s.', $name));
                }

                $checksum = hash('sha256', $sql);
                $recordedChecksum = $this->appliedChecksum($name);

                if ($recordedChecksum !== false) {
                    if ($recordedChecksum === null || $recordedChecksum === '') {
                        $this->backfillChecksum($name, $checksum);
                        continue;
                    }

                    if (!hash_equals($recordedChecksum, $checksum)) {
                        throw new RuntimeException(sprintf(
                            'Миграция %s была изменена после применения. Создайте новую миграцию вместо изменения существующей.',
                            $name,
                        ));
                    }

                    continue;
                }

                $this->applyMigration($name, $sql, $checksum);
                $applied[] = $name;
            }

            return $applied;
        } finally {
            $this->db->query("SELECT pg_advisory_unlock(hashtext('ultima_vox_cms_migrations'))");
        }
    }

    private function ensureTrackingSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (' .
            'migration VARCHAR(255) PRIMARY KEY, ' .
            'checksum CHAR(64) NULL, ' .
            'applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP)'
        );
        $this->db->exec('ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS checksum CHAR(64) NULL');
    }

    private function applyMigration(string $name, string $sql, string $checksum): void
    {
        $this->db->beginTransaction();

        try {
            $this->db->exec($sql);

            $statement = $this->db->prepare(
                'INSERT INTO schema_migrations (migration, checksum) VALUES (:migration, :checksum)'
            );
            $statement->execute([
                'migration' => $name,
                'checksum' => $checksum,
            ]);

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /** @return string|null|false false means the migration was not applied. */
    private function appliedChecksum(string $migration): string|null|false
    {
        $statement = $this->db->prepare(
            'SELECT checksum FROM schema_migrations WHERE migration = :migration LIMIT 1'
        );
        $statement->execute(['migration' => $migration]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return false;
        }

        $checksum = $row['checksum'] ?? null;
        return is_string($checksum) ? trim($checksum) : null;
    }

    private function backfillChecksum(string $migration, string $checksum): void
    {
        $statement = $this->db->prepare(
            'UPDATE schema_migrations SET checksum = :checksum WHERE migration = :migration AND checksum IS NULL'
        );
        $statement->execute([
            'migration' => $migration,
            'checksum' => $checksum,
        ]);
    }
}
