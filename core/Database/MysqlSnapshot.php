<?php

declare(strict_types=1);

namespace Core\Database;

use PDO;
use RuntimeException;
use Throwable;

/** A private, streaming recovery snapshot for a dedicated legacy CMS database. */
final class MysqlSnapshot
{
    private const MAX_BYTES = 268435456;

    public static function create(PDO $db, string $path): string
    {
        if ($db->inTransaction()) throw new RuntimeException('Snapshot requires its own transaction.');
        $tables = $db->query('SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_TYPE AS kind FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tables as $table) {
            self::identifier($table['name']);
            if ($table['kind'] !== 'BASE TABLE' || strcasecmp((string) $table['engine'], 'InnoDB') !== 0) {
                throw new RuntimeException('Legacy online updates require a dedicated database containing only InnoDB tables.');
            }
        }
        $routines = (int) $db->query('SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema=DATABASE()')->fetchColumn();
        $events = (int) $db->query('SELECT COUNT(*) FROM information_schema.events WHERE event_schema=DATABASE()')->fetchColumn();
        if ($routines !== 0 || $events !== 0) throw new RuntimeException('Legacy online snapshot does not support stored routines/events; no files changed.');
        $triggers = $db->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC);
        $stream = fopen($path, 'x');
        if ($stream === false) throw new RuntimeException('Cannot create database recovery snapshot.');
        chmod($path, 0600);
        $bytes = 0;
        $write = static function (array $record) use ($stream, &$bytes): void {
            $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
            $bytes += strlen($line);
            if ($bytes > self::MAX_BYTES || fwrite($stream, $line) !== strlen($line)) {
                throw new RuntimeException('Legacy database snapshot exceeds 256 MiB or disk space is exhausted; no update files have been replaced.');
            }
        };
        $buffered = $db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        try {
            $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->beginTransaction();
            $write(['format' => 1]);
            foreach ($tables as $table) {
                $name = self::identifier($table['name']);
                $schema = $db->query('SHOW CREATE TABLE ' . $name)->fetch(PDO::FETCH_NUM);
                $write(['table' => $table['name'], 'ddl' => $schema[1]]);
                $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                $rows = $db->query('SELECT * FROM ' . $name);
                while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                    // Preserve binary values and NULL; generated columns are excluded on restore.
                    $write(['row' => $table['name'], 'values' => array_map(static fn ($value) => $value === null ? null : base64_encode((string) $value), $row)]);
                }
                $rows->closeCursor();
                $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
            }
            foreach ($triggers as $trigger) {
                $write(['trigger' => 'CREATE TRIGGER ' . self::identifier($trigger['Trigger']) . ' ' . $trigger['Timing'] . ' ' . $trigger['Event'] . ' ON ' . self::identifier($trigger['Table']) . ' FOR EACH ROW ' . $trigger['Statement']]);
            }
            $write(['complete' => true]);
            $db->commit();
            if (!fflush($stream)) throw new RuntimeException('Cannot flush recovery snapshot.');
            if (function_exists('fsync') && !fsync($stream)) throw new RuntimeException('Cannot sync recovery snapshot.');
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        } finally {
            $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
            fclose($stream);
        }
        return hash_file('sha256', $path);
    }

    public static function restore(PDO $db, string $path, string $sha256): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $sha256) || !is_file($path) || !hash_equals($sha256, hash_file('sha256', $path))) {
            throw new RuntimeException('Database snapshot checksum mismatch; maintenance retained.');
        }
        // Validate the complete stream before deleting any table.
        $stream = fopen($path, 'r');
        if ($stream === false) throw new RuntimeException('Cannot read recovery snapshot.');
        $complete = false; $first = true;
        while (($line = fgets($stream)) !== false) {
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if ($first && ($record['format'] ?? null) !== 1) throw new RuntimeException('Unknown snapshot format.');
            $first = false;
            $complete = ($record['complete'] ?? false) === true;
        }
        if (!$complete) throw new RuntimeException('Incomplete recovery snapshot.');
        rewind($stream);
        $generated = [];
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($db->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $table) {
                if ($table[1] !== 'BASE TABLE') throw new RuntimeException('Unexpected view in recovery database.');
                $db->exec('DROP TABLE ' . self::identifier($table[0]));
            }
            while (($line = fgets($stream)) !== false) {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (isset($record['ddl'])) {
                    $db->exec($record['ddl']);
                    $generated[$record['table']] = [];
                    foreach ($db->query('SHOW FULL COLUMNS FROM ' . self::identifier($record['table']))->fetchAll(PDO::FETCH_ASSOC) as $column) {
                        if (preg_match('/(?:STORED|VIRTUAL) GENERATED/i', (string) $column['Extra']) === 1) $generated[$record['table']][$column['Field']] = true;
                    }
                } elseif (isset($record['row'])) {
                    $values = array_diff_key($record['values'], $generated[$record['row']] ?? []);
                    $columns = array_map(self::identifier(...), array_keys($values));
                    $q = $db->prepare('INSERT INTO ' . self::identifier($record['row']) . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
                    $q->execute(array_values(array_map(static fn ($value) => $value === null ? null : base64_decode($value, true), $values)));
                } elseif (isset($record['trigger'])) {
                    $db->exec($record['trigger']);
                }
            }
        } finally {
            $db->exec('SET FOREIGN_KEY_CHECKS=1');
            fclose($stream);
        }
    }

    private static function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) throw new RuntimeException('Unsupported database identifier in recovery snapshot.');
        return '`' . $name . '`';
    }
}
