<?php

declare(strict_types=1);

namespace Core\Database;

use PDO;
use RuntimeException;

/** MySQL DDL implicitly commits: persist intent first and never retry a dirty migration. */
final readonly class MysqlMigrationRunner
{
    public function __construct(private PDO $db) {}

    public function migrate(string $path, ?string $module = null): array
    {
        if ($this->db->inTransaction()) throw new RuntimeException('MySQL DDL нельзя выполнять внутри транзакции приложения.');
        $path = SqlDialect::migrationPath($this->db, $path);
        if (!is_dir($path)) return [];
        $table = $module === null ? 'schema_migrations' : 'module_schema_migrations';
        $key = 'ultima_vox_cms_update';
        SqlDialect::lock($this->db, $key);
        try {
            if ($module === null) {
                $this->db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(255) PRIMARY KEY, checksum CHAR(64) NOT NULL, dirty BOOLEAN NOT NULL DEFAULT FALSE, applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)) ENGINE=InnoDB');
            } elseif (!SqlDialect::tableExists($this->db, $table)) {
                throw new RuntimeException('Сначала примените миграции Core.');
            }
            $params = $module === null ? [] : ['module' => $module];
            $scope = $module === null ? '' : ' AND module_code = :module';
            $dirty = $this->db->prepare('SELECT migration FROM ' . $table . ' WHERE dirty = TRUE' . $scope . ' LIMIT 1');
            $dirty->execute($params);
            if (($failed = $dirty->fetchColumn()) !== false) throw new RuntimeException('Незавершённая миграция MySQL: ' . $failed . '. Сохраните БД и журнал; требуется восстановление, автоматический повтор запрещён.');
            $files = glob($path . '/*.sql');
            if ($files === false) throw new RuntimeException('Не прочитан каталог миграций.');
            sort($files, SORT_STRING);
            $applied = [];
            foreach ($files as $file) {
                $name = basename($file);
                $sql = file_get_contents($file);
                if (!is_string($sql)) throw new RuntimeException('Не прочитана миграция: ' . $name);
                $checksum = hash('sha256', $sql);
                $q = $this->db->prepare('SELECT checksum FROM ' . $table . ' WHERE migration = :migration' . $scope);
                $q->execute($params + ['migration' => $name]);
                $recorded = $q->fetchColumn();
                if ($recorded !== false) {
                    if (!hash_equals(trim((string) $recorded), $checksum)) throw new RuntimeException('Изменена применённая миграция: ' . $name);
                    continue;
                }
                $q = $this->db->prepare('INSERT INTO ' . $table . ' (' . ($module === null ? '' : 'module_code, ') . 'migration, checksum, dirty) VALUES (' . ($module === null ? '' : ':module, ') . ':migration, :checksum, TRUE)');
                $q->execute($params + ['migration' => $name, 'checksum' => $checksum]);
                SqlDialect::runScript($this->db, $sql);
                $q = $this->db->prepare('UPDATE ' . $table . ' SET dirty = FALSE WHERE migration = :migration' . $scope);
                $q->execute($params + ['migration' => $name]);
                $applied[] = $name;
            }
            return $applied;
        } finally { SqlDialect::unlock($this->db, $key); }
    }
}
