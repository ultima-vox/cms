<?php

declare(strict_types=1);

namespace Core\Database;

use PDO;
use PDOStatement;
use RuntimeException;

/** Small SQL fragments, not a query translator or an ORM. Domain SQL stays in its module. */
final class SqlDialect
{
    public static function mysql(PDO $db): bool
    {
        return $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    public static function returningId(PDO $db): string
    {
        return self::mysql($db) ? '' : ' RETURNING id';
    }

    public static function insertedId(PDO $db, PDOStatement $statement): int
    {
        return (int) (self::mysql($db) ? $db->lastInsertId() : $statement->fetchColumn());
    }

    /** Identifiers are application constants, never request input. */
    public static function upsert(PDO $db, array $keys, array $updates = []): string
    {
        foreach ([...$keys, ...$updates] as $column) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/D', $column)) throw new RuntimeException('Invalid SQL identifier.');
        }
        if ($keys === []) throw new RuntimeException('Upsert requires a unique key.');
        if (self::mysql($db)) {
            return ' ON DUPLICATE KEY UPDATE ' . ($updates === [] ? $keys[0] . ' = ' . $keys[0]
                : implode(', ', array_map(static fn ($c) => $c . ' = VALUES(' . $c . ')', $updates)));
        }
        return ' ON CONFLICT (' . implode(', ', $keys) . ') DO ' . ($updates === [] ? 'NOTHING'
            : 'UPDATE SET ' . implode(', ', array_map(static fn ($c) => $c . ' = EXCLUDED.' . $c, $updates)));
    }

    public static function tableExists(PDO $db, string $name): bool
    {
        $sql = self::mysql($db)
            ? 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :name'
            : 'SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :name';
        $q = $db->prepare($sql); $q->execute(['name' => $name]);
        return $q->fetchColumn() !== false;
    }

    public static function lock(PDO $db, string $key, bool $transaction = false): void
    {
        if (!self::mysql($db)) {
            $q = $db->prepare('SELECT ' . ($transaction ? 'pg_advisory_xact_lock' : 'pg_advisory_lock') . '(hashtext(:key))');
            $q->execute(['key' => $key]);
            return;
        }
        if ($transaction) {
            if (!$db->inTransaction()) throw new RuntimeException('Transactional lock requires a transaction.');
            $q = $db->prepare('INSERT INTO runtime_locks(lock_key) VALUES(:key) ON DUPLICATE KEY UPDATE lock_key = lock_key');
            $q->execute(['key' => hash('sha256', $key)]);
            $q = $db->prepare('SELECT lock_key FROM runtime_locks WHERE lock_key = :key FOR UPDATE');
            $q->execute(['key' => hash('sha256', $key)]);
            return;
        }
        $q = $db->prepare('SELECT GET_LOCK(CONCAT(LEFT(DATABASE(), 16), :key), 10)');
        $q->execute(['key' => substr(hash('sha256', $key), 0, 48)]);
        if ((int) $q->fetchColumn() !== 1) throw new RuntimeException('Не удалось получить блокировку БД. Повторите позже.');
    }

    public static function unlock(PDO $db, string $key): void
    {
        $q = $db->prepare(self::mysql($db)
            ? 'SELECT RELEASE_LOCK(CONCAT(LEFT(DATABASE(), 16), :key))'
            : 'SELECT pg_advisory_unlock(hashtext(:key))');
        $q->execute(['key' => self::mysql($db) ? substr(hash('sha256', $key), 0, 48) : $key]);
    }

    public static function migrationPath(PDO $db, string $path): string
    {
        if (!self::mysql($db)) return $path;
        if (is_dir($path) && !is_dir($path . '/mysql') && glob($path . '/*.sql') !== []) {
            throw new RuntimeException('Пакет не предоставляет миграции MySQL/MariaDB: ' . basename(dirname($path)));
        }
        return $path . '/mysql';
    }

    public static function runScript(PDO $db, string $sql): void
    {
        if (!self::mysql($db)) { $db->exec($sql); return; }
        // Explicit boundaries preserve trigger bodies; no SQL syntax is rewritten.
        foreach (preg_split('/^-- cms:statement\s*$/m', $sql) ?: [] as $statement) {
            if (trim($statement) !== '') $db->exec($statement);
        }
    }
}
