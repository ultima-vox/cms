<?php

declare(strict_types=1);

namespace Core\Extension;

use PDO;
use RuntimeException;
use Throwable;

final class ModuleMigrationRunner
{
    public function __construct(
        private readonly PDO $db,
        private readonly string $rootPath,
    ) {
    }

    /** @return list<string> */
    public function migrate(string $moduleCode): array
    {
        $moduleCode = $this->normalizeModuleCode($moduleCode);
        $this->assertMigrationRegistryExists();
        $this->assertModuleExists($moduleCode);

        $path = $this->rootPath . '/modules/' . $moduleCode . '/migrations';
        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . '/*.sql');
        if ($files === false) {
            throw new RuntimeException('Не удалось прочитать каталог миграций модуля: ' . $moduleCode);
        }
        sort($files, SORT_STRING);

        $this->lock($moduleCode);
        try {
            return $this->apply($moduleCode, $files);
        } finally {
            $this->unlock($moduleCode);
        }
    }

    /** @param list<string> $files @return list<string> */
    private function apply(string $moduleCode, array $files): array
    {
        $applied = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,254}\\.sql$/', $name)) {
                throw new RuntimeException('Некорректное имя миграции модуля: ' . $name);
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Не удалось прочитать миграцию модуля: ' . $name);
            }
            $checksum = hash('sha256', $sql);

            $recorded = $this->recordedChecksum($moduleCode, $name);
            if ($recorded !== null) {
                if (!hash_equals($recorded, $checksum)) {
                    throw new RuntimeException(sprintf(
                        'Миграция модуля %s/%s была изменена после применения.',
                        $moduleCode,
                        $name,
                    ));
                }
                continue;
            }

            $this->db->beginTransaction();
            try {
                if (trim($sql) !== '') {
                    $this->db->exec($sql);
                }

                $statement = $this->db->prepare(
                    <<<'SQL'
                    INSERT INTO module_schema_migrations (module_code, migration, checksum)
                    VALUES (:module_code, :migration, :checksum)
                    SQL
                );
                $statement->execute([
                    'module_code' => $moduleCode,
                    'migration' => $name,
                    'checksum' => $checksum,
                ]);
                $this->db->commit();
                $applied[] = $name;
            } catch (Throwable $exception) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $exception;
            }
        }

        return $applied;
    }

    private function recordedChecksum(string $moduleCode, string $migration): ?string
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT checksum
            FROM module_schema_migrations
            WHERE module_code = :module_code
              AND migration = :migration
            LIMIT 1
            SQL
        );
        $statement->execute([
            'module_code' => $moduleCode,
            'migration' => $migration,
        ]);
        $value = $statement->fetchColumn();

        return is_string($value) ? trim($value) : null;
    }

    private function assertMigrationRegistryExists(): void
    {
        $statement = $this->db->query(
            "SELECT to_regclass('public.module_schema_migrations')"
        );
        $table = $statement->fetchColumn();
        if ($table !== 'module_schema_migrations') {
            throw new RuntimeException('Сначала примените миграции Core: php bin/console migrate');
        }
    }

    private function assertModuleExists(string $moduleCode): void
    {
        $modulePath = $this->rootPath . '/modules/' . $moduleCode;
        if (!is_dir($modulePath)) {
            throw new RuntimeException('Модуль не найден: ' . $moduleCode);
        }

        foreach ((new ModuleLoader($this->rootPath))->discover() as $manifest) {
            if ($manifest->code === $moduleCode) {
                return;
            }
        }

        throw new RuntimeException('Manifest модуля не найден: ' . $moduleCode);
    }

    private function lock(string $moduleCode): void
    {
        $statement = $this->db->prepare('SELECT pg_advisory_lock(hashtext(:lock_key))');
        $statement->execute(['lock_key' => 'uvcms:module-migrate:' . $moduleCode]);
    }

    private function unlock(string $moduleCode): void
    {
        $statement = $this->db->prepare('SELECT pg_advisory_unlock(hashtext(:lock_key))');
        $statement->execute(['lock_key' => 'uvcms:module-migrate:' . $moduleCode]);
    }

    private function normalizeModuleCode(string $moduleCode): string
    {
        $moduleCode = strtolower(trim($moduleCode));
        if (!preg_match('/^[a-z][a-z0-9._-]{0,79}$/', $moduleCode)) {
            throw new RuntimeException('Некорректный код модуля.');
        }

        return $moduleCode;
    }
}
