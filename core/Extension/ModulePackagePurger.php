<?php

declare(strict_types=1);

namespace Core\Extension;

use PDO;
use RuntimeException;
use Throwable;

final readonly class ModulePackagePurger
{
    private const MAX_PURGE_SCRIPT_SIZE = 1_048_576; // 1 MiB

    public function __construct(
        private PDO $db,
        private string $rootPath,
    ) {
    }

    public function purge(string $moduleCode, string $confirmation): void
    {
        if ($confirmation !== $moduleCode) {
            throw new RuntimeException(sprintf(
                'Destructive purge requires exact confirmation: --confirm=%s.',
                $moduleCode,
            ));
        }

        $inventory = new ModulePackageInventoryRepository($this->db);
        $record = $inventory->find($moduleCode);
        if ($record === null || $record['source'] !== 'package') {
            throw new RuntimeException(sprintf(
                'Module "%s" is not an installer-managed package and cannot be purged.',
                $moduleCode,
            ));
        }

        $stateRepository = new ModuleStateRepository($this->db);
        $states = $stateRepository->all();
        $state = $states[$moduleCode] ?? null;
        if (!is_array($state)) {
            throw new RuntimeException(sprintf(
                'Module "%s" is not synchronized. Run extensions:sync before purging it.',
                $moduleCode,
            ));
        }
        if ($state['is_enabled']) {
            throw new RuntimeException(sprintf(
                'Module "%s" must be disabled before destructive purge.',
                $moduleCode,
            ));
        }

        $manifests = (new ModuleLoader($this->rootPath))->discover();
        $targetManifest = null;
        foreach ($manifests as $manifest) {
            if ($manifest->code === $moduleCode) {
                $targetManifest = $manifest;
                continue;
            }
            if (array_key_exists($moduleCode, $manifest->requires)) {
                throw new RuntimeException(sprintf(
                    'Module "%s" cannot be purged because module "%s" depends on it.',
                    $moduleCode,
                    $manifest->code,
                ));
            }
        }

        if (!$targetManifest instanceof ModuleManifest) {
            throw new RuntimeException(sprintf('Module "%s" manifest is missing.', $moduleCode));
        }
        if ($targetManifest->purgeScripts === []) {
            throw new RuntimeException(sprintf(
                'Module "%s" does not declare purge SQL. Use modules:remove to preserve its data.',
                $moduleCode,
            ));
        }

        $target = $this->modulePath($moduleCode);
        $scripts = $this->loadPurgeScripts($target, $targetManifest->purgeScripts);
        $backup = $this->rootPath . '/modules/.purge-backup-' . $moduleCode . '-' . bin2hex(random_bytes(8));
        if (!rename($target, $backup)) {
            throw new RuntimeException('Unable to stage module files for purge rollback.');
        }

        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT pg_advisory_xact_lock(hashtext(:lock_key))');
            $lock->execute(['lock_key' => 'ultima-vox:module-purge:' . $moduleCode]);

            foreach ($scripts as $path => $sql) {
                try {
                    $this->db->exec($sql);
                } catch (Throwable $exception) {
                    throw new RuntimeException(sprintf('Purge SQL failed: %s.', $path), 0, $exception);
                }
            }

            $deleteMigrations = $this->db->prepare(
                'DELETE FROM module_schema_migrations WHERE module_code = :module_code'
            );
            $deleteMigrations->execute(['module_code' => $moduleCode]);

            $inventory->remove($moduleCode);
            $stateRepository->remove($moduleCode);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (!rename($backup, $target)) {
                throw new RuntimeException(
                    'Module purge failed and automatic filesystem rollback also failed.',
                    0,
                    $exception,
                );
            }
            throw $exception;
        }

        // The destructive database transaction is committed. A leftover hidden backup on
        // filesystem cleanup failure is safer than deleting data before the transaction.
        $this->removeTree($backup);
    }

    /**
     * @param list<string> $paths
     * @return array<string, string>
     */
    private function loadPurgeScripts(string $modulePath, array $paths): array
    {
        $root = realpath($modulePath);
        if ($root === false || !is_dir($root) || is_link($modulePath)) {
            throw new RuntimeException('Installed module directory is missing or invalid.');
        }

        $scripts = [];
        foreach ($paths as $relative) {
            $candidate = $modulePath . '/' . $relative;
            $real = realpath($candidate);
            if ($real === false
                || !is_file($real)
                || is_link($candidate)
                || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Declared purge SQL is missing or unsafe: ' . $relative);
            }

            $size = filesize($real);
            if (!is_int($size) || $size < 1 || $size > self::MAX_PURGE_SCRIPT_SIZE) {
                throw new RuntimeException('Declared purge SQL has invalid size: ' . $relative);
            }

            $sql = file_get_contents($real);
            if (!is_string($sql) || trim($sql) === '') {
                throw new RuntimeException('Declared purge SQL is empty or unreadable: ' . $relative);
            }
            $scripts[$relative] = $sql;
        }

        return $scripts;
    }

    private function modulePath(string $moduleCode): string
    {
        if (!preg_match('/^[a-z][a-z0-9._-]{0,79}$/', $moduleCode)) {
            throw new RuntimeException('Refusing to use an invalid module path.');
        }

        return $this->rootPath . '/modules/' . $moduleCode;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            throw new RuntimeException('Unable to inspect purged module backup: ' . $path);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $child = $path . '/' . $item;
            if (is_dir($child) && !is_link($child)) {
                $this->removeTree($child);
            } elseif (!unlink($child)) {
                throw new RuntimeException('Unable to remove purged module file: ' . $child);
            }
        }

        if (!rmdir($path)) {
            throw new RuntimeException('Unable to remove purged module backup: ' . $path);
        }
    }
}
