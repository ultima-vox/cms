<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Updater;

use PDO;
use Core\MigrationRunner;
use Core\Extension\ModuleMigrationCoordinator;
use Core\Extension\ModuleManager;
use RuntimeException;
use Throwable;
use ZipArchive;

final readonly class FileUpdater
{
    public function __construct(private string $root, private ?PDO $db = null)
    {
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        $data = json_decode((string) @file_get_contents($this->root . '/release.json'), true);
        if (!is_array($data) || !isset($data['version'], $data['files'], $data['schema'])
            || !in_array($data['layout'] ?? '', ['webroot', 'public'], true)) {
            throw new RuntimeException('Нет метаданных сборки. Установите стартовый пакет обновлялки.');
        }
        return $data;
    }

    /** @param array<string, mixed> $offer */
    public function apply(string $archiveBytes, array $offer): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Включите расширение zip для PHP сайта.');
        }
        if (!isset($offer['sha256'], $offer['version']) || !hash_equals($offer['sha256'], hash('sha256', $archiveBytes))) {
            throw new RuntimeException('Контрольная сумма архива не совпала.');
        }
        $updates = $this->root . '/storage/updates';
        $this->directory($updates);
        $lock = fopen($updates . '/apply.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('Другое обновление уже выполняется.');
        }
        $requestLock = null;
        $resumeRequest = false;
        $backup = $updates . '/backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $journal = [];
        $zipPath = $updates . '/download-' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new ZipArchive();
        $opened = false;
        $changed = false;
        $transaction = null;
        $mysql = $this->db !== null && \Core\Database\SqlDialect::mysql($this->db);
        $mysqlSnapshot = null;
        $mysqlDbLock = false;
        $commitAttempted = false;
        $metadata = ['from' => '', 'to' => $offer['version'], 'started_at' => gmdate(DATE_ATOM)];
        try {
            $current = $this->current();
            $metadata['from'] = $current['version'];
            if (version_compare($offer['version'], $current['version'], '<=')) {
                throw new RuntimeException('Эта версия уже установлена или устарела.');
            }
            $this->write($zipPath, $archiveBytes);
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Некорректный ZIP обновления.');
            }
            $opened = true;
            if ($zip->numFiles > 12000) throw new RuntimeException('Слишком много файлов в архиве.');
            if (is_file($updates . '/maintenance.json')) throw new RuntimeException('Предыдущее обновление не завершено. Требуется восстановление из резервной копии.');
            $manifestBytes = $zip->getFromName('update.json', 2_000_001);
            if ($manifestBytes === false || strlen($manifestBytes) > 2_000_000) {
                throw new RuntimeException('Манифест архива отсутствует или слишком велик.');
            }
            $incoming = json_decode($manifestBytes, true, 32, JSON_THROW_ON_ERROR);
            if (($incoming['version'] ?? '') !== $offer['version'] || !is_array($incoming['files'] ?? null)
                || !is_array($incoming['schema'] ?? null) || count($incoming['files']) > 10000) {
                throw new RuntimeException('Некорректная версия или список файлов.');
            }
            $oldSchema = $current['schema']; $newSchema = $incoming['schema'];
            ksort($oldSchema); ksort($newSchema);
            foreach ($oldSchema as $path => $checksum) {
                if (($newSchema[$path] ?? null) !== $checksum) throw new RuntimeException('Релиз изменяет или удаляет существующую миграцию: ' . $path);
            }
            if ($oldSchema !== $newSchema && $this->db === null) {
                throw new RuntimeException('Обновление схемы требует подключения к БД.');
            }
            $actualSchema = [];
            foreach ($incoming['files'] as $name => $hash) {
                if (str_ends_with($name, '.sql') && (str_starts_with($name, 'database/migrations/') || str_contains($name, '/migrations/'))) $actualSchema[$name] = $hash;
            }
            ksort($actualSchema);
            if ($actualSchema !== $newSchema) throw new RuntimeException('Некорректные контрольные суммы схемы БД.');
            $expected = ['update.json' => true];
            $total = 0;
            foreach ($incoming['files'] as $name => $hash) {
                $this->validPath($name);
                if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                    throw new RuntimeException('Некорректная контрольная сумма файла.');
                }
                $entry = 'payload/' . $name;
                $stat = $zip->statName($entry);
                if ($stat === false || $stat['size'] > 20_000_000 || ($total += $stat['size']) > 150_000_000) {
                    throw new RuntimeException('Некорректный размер содержимого архива.');
                }
                $bytes = $zip->getFromName($entry);
                if ($bytes === false || !hash_equals($hash, hash('sha256', $bytes))) {
                    throw new RuntimeException('Повреждён файл обновления: ' . $name);
                }
                $expected[$entry] = true;
            }
            if (count($expected) !== $zip->numFiles) {
                throw new RuntimeException('Архив содержит лишние или повторяющиеся файлы.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                $os = $attributes = 0;
                if (!isset($expected[$name]) || !$zip->getExternalAttributesIndex($i, $os, $attributes)
                    || (($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('Архив содержит недопустимую запись.');
                }
            }
            $resumeRequest = class_exists(\Core\Http\DeploymentGuard::class) && \Core\Http\DeploymentGuard::held($this->root);
            if ($resumeRequest) \Core\Http\DeploymentGuard::release($this->root);
            $requestLock = fopen($updates . '/requests.lock', 'c');
            if ($requestLock === false || !flock($requestLock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Сайт обрабатывает запросы. Повторите обновление через несколько секунд.');
            }
            $plan = [];
            foreach ($incoming['files'] as $name => $hash) {
                foreach ($this->targets($name, $current['layout']) as $target) {
                    $this->inspect($target, $current['files'][$name] ?? null, $hash);
                    if (!is_file($target) || !hash_equals($hash, hash_file('sha256', $target))) {
                        $plan[$target] = $name;
                    }
                }
            }
            foreach ($current['files'] as $name => $hash) {
                if (isset($incoming['files'][$name])) continue;
                $this->validPath($name);
                foreach ($this->targets($name, $current['layout']) as $target) {
                    $this->inspect($target, $hash, null);
                    if (is_file($target)) $plan[$target] = null;
                }
            }
            $plan[$this->root . '/release.json'] = '@release';
            $this->directory($backup);
            foreach ($plan as $target => $source) {
                $index = count($journal);
                $old = is_file($target) ? file_get_contents($target) : null;
                if ($old === false) throw new RuntimeException('Не прочитан исходный файл.');
                if ($old !== null) $this->write($backup . '/' . $index . '.bak', $old);
                $journal[] = ['target' => $target, 'backup' => $old !== null ? $index . '.bak' : null, 'mode' => $old !== null ? (fileperms($target) & 0777) : 0644];
            }
            if ($this->db !== null) {
                if ($this->db->inTransaction()) throw new RuntimeException('Обновление нельзя запускать внутри другой транзакции.');
                if ($mysql) {
                    \Core\Database\SqlDialect::lock($this->db, 'ultima_vox_cms_update');
                    $mysqlDbLock = true;
                    $mysqlSnapshot = \Core\Database\MysqlSnapshot::create($this->db, $backup . '/database.jsonl');
                    $metadata['mysql_snapshot_sha256'] = $mysqlSnapshot;
                    $metadata['db_commit_token'] = bin2hex(random_bytes(16));
                } else {
                    $this->db->beginTransaction();
                    $transaction = (string) $this->db->query('SELECT pg_current_xact_id()')->fetchColumn();
                }
            }
            $metadata['db_transaction'] = $transaction;
            $this->write($backup . '/journal.json', json_encode($metadata + ['status' => 'prepared', 'files' => $journal], JSON_THROW_ON_ERROR));
            $this->write($updates . '/maintenance.json', json_encode(['backup' => basename($backup)], JSON_THROW_ON_ERROR));
            $changed = true;
            foreach ($plan as $target => $source) {
                if ($source === null) {
                    if (!unlink($target)) throw new RuntimeException('Не удалён устаревший файл.');
                } else {
                    $bytes = $source === '@release'
                        ? json_encode($incoming + ['layout' => $current['layout']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
                        : $zip->getFromName('payload/' . $source);
                    $this->directory(dirname($target));
                    $mode = is_file($target) ? (fileperms($target) & 0777) : (str_starts_with((string) $source, 'bin/') ? 0755 : 0644);
                    $this->write($target, $bytes, $mode);
                }
                if (function_exists('opcache_invalidate')) @opcache_invalidate($target, true);
            }
            if ($this->db !== null) {
                (new MigrationRunner($this->db, $this->root . '/database/migrations'))->migrate();
                (new ModuleMigrationCoordinator($this->db, $this->root))->migrateEnabled();
                if ($mysql) $this->db->beginTransaction();
                (new ModuleManager($this->db, $this->root))->sync();
                $this->db->exec('INSERT INTO extension_sync_jobs(singleton) VALUES(TRUE)' . \Core\Database\SqlDialect::upsert($this->db, ['singleton'], ['requested_at']));
                if ($mysql) {
                    $marker = $this->db->prepare('INSERT INTO update_commits(token) VALUES(:token)');
                    $marker->execute(['token' => $metadata['db_commit_token']]);
                }
                $commitAttempted = true;
                $this->db->commit();
            }
            $this->write($backup . '/journal.json', json_encode($metadata + ['status' => 'complete', 'files' => $journal], JSON_THROW_ON_ERROR));
            if (function_exists('opcache_reset')) @opcache_reset();
            if (!unlink($updates . '/maintenance.json')) throw new RuntimeException('Не снят режим обслуживания.');
            return $backup;
        } catch (Throwable $exception) {
            if ($commitAttempted) {
                // A lost DB connection during COMMIT has an unknown outcome. Keep maintenance
                // and let recovery consult PostgreSQL's durable transaction status.
                throw new RuntimeException('Не удалось завершить обновление. Запустите bin/update-recover.php для проверки результата транзакции.', 0, $exception);
            }
            if ($this->db?->inTransaction()) $this->db->rollBack();
            if ($mysql && $mysqlSnapshot !== null && $changed) {
                try { \Core\Database\MysqlSnapshot::restore($this->db, $backup . '/database.jsonl', $mysqlSnapshot); }
                catch (Throwable $restoreError) {
                    throw new RuntimeException('Не удалось восстановить MySQL. Режим обслуживания сохранён; запустите bin/update-recover.php с этой резервной копией.', 0, $restoreError);
                }
            }
            if ($changed) {
                $rollbackFailed = false;
                foreach (array_reverse($journal) as $entry) {
                    try {
                        if ($entry['backup'] !== null) {
                            $this->write($entry['target'], (string) file_get_contents($backup . '/' . $entry['backup']), $entry['mode']);
                        } elseif (is_file($entry['target']) && !unlink($entry['target'])) {
                            throw new RuntimeException('Не удалён новый файл.');
                        }
                        if (function_exists('opcache_invalidate')) @opcache_invalidate($entry['target'], true);
                    } catch (Throwable) { $rollbackFailed = true; }
                }
                $this->write($backup . '/journal.json', json_encode($metadata + ['status' => $rollbackFailed ? 'recovery_required' : 'rolled_back', 'error' => $exception->getMessage(), 'files' => $journal], JSON_THROW_ON_ERROR));
                if (!$rollbackFailed && is_file($updates . '/maintenance.json')) unlink($updates . '/maintenance.json');
                if ($rollbackFailed) {
                    throw new RuntimeException('Ошибка отката. Сохраните резервную копию ' . basename($backup) . ' и восстановите файлы вручную.', 0, $exception);
                }
            }
            throw $exception;
        } finally {
            if ($mysqlDbLock) \Core\Database\SqlDialect::unlock($this->db, 'ultima_vox_cms_update');
            if ($opened) $zip->close();
            if (is_file($zipPath)) unlink($zipPath);
            if (is_resource($requestLock)) { flock($requestLock, LOCK_UN); fclose($requestLock); }
            if ($resumeRequest) \Core\Http\DeploymentGuard::enter($this->root);
            flock($lock, LOCK_UN); fclose($lock);
        }
    }

    private function validPath(mixed $path): void
    {
        if (!is_string($path) || str_contains($path, '\\') || str_contains($path, '//')
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\x00-\x20]~', $path)
            || !preg_match('~^(?:(?:core|modules|templates|public|database|installer|deploy|vendor|bin)/[a-zA-Z0-9_./@+-]+|composer\.(?:json|lock))$~D', $path)
            || preg_match('~(?:^|/)(?:\.env(?:\..*)?|\.git|storage)(?:/|$)~', $path)) {
            throw new RuntimeException('Недопустимый путь файла обновления.');
        }
    }

    /** @return list<string> */
    private function targets(string $name, string $layout): array
    {
        $targets = [$this->root . '/' . $name];
        if ($layout === 'webroot') {
            if (str_starts_with($name, 'public/assets/')) $targets[] = dirname($this->root) . '/' . substr($name, 7);
            if ($name === 'deploy/webroot/index.php') $targets[] = dirname($this->root) . '/index.php';
            if ($name === 'deploy/webroot/.htaccess') $targets[] = dirname($this->root) . '/.htaccess';
        }
        return $targets;
    }

    private function inspect(string $target, ?string $oldHash, ?string $newHash): void
    {
        $cursor = $target;
        while (strlen($cursor) >= strlen(dirname($this->root))) {
            if (is_link($cursor)) throw new RuntimeException('Символьные ссылки не поддерживаются при обновлении.');
            if ($cursor === dirname($this->root)) break;
            $cursor = dirname($cursor);
        }
        if (file_exists($target) && !is_file($target)) throw new RuntimeException('Путь занят каталогом: ' . basename($target));
        if (is_file($target)) {
            $actual = hash_file('sha256', $target);
            if (($newHash === null || !hash_equals($newHash, $actual)) && ($oldHash === null || !hash_equals($oldHash, $actual))) {
                throw new RuntimeException('Локально изменён файл: ' . substr($target, strlen(dirname($this->root)) + 1) . '. Обновление остановлено.');
            }
        }
        $parent = dirname($target);
        while (!is_dir($parent)) $parent = dirname($parent);
        if (!is_writable($parent)) throw new RuntimeException('PHP не может записывать в каталог кода. Проверьте права владельца сайта.');
    }

    private function directory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) throw new RuntimeException('Не создан каталог обновления.');
    }

    private function write(string $target, string $bytes, int $mode = 0600): void
    {
        $temporary = $target . '.update-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !chmod($temporary, $mode) || !rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException('Не записан файл: ' . basename($target));
        }
    }
}
