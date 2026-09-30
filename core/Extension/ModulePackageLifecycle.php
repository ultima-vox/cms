<?php

declare(strict_types=1);

namespace Core\Extension;

use PDO;
use RuntimeException;
use Throwable;

final readonly class ModulePackageLifecycle
{
    private ModulePackageInventoryRepository $inventory;

    public function __construct(
        private PDO $db,
        private string $rootPath,
    ) {
        $this->inventory = new ModulePackageInventoryRepository($db);
    }

    public function install(string $archivePath): ModuleManifest
    {
        $checksum = hash_file('sha256', $archivePath);
        if (!is_string($checksum) || strlen($checksum) !== 64) {
            throw new RuntimeException('Unable to calculate module package SHA-256 checksum.');
        }

        // Fail before touching the filesystem if Core migrations are incomplete.
        $this->db->query('SELECT 1 FROM module_package_inventory LIMIT 1');

        $manifest = (new ModulePackageInstaller($this->rootPath))->install($archivePath);

        try {
            $this->inventory->recordInstall($manifest->code, $manifest->version, $checksum);
        } catch (Throwable $exception) {
            $this->removeInstalledDirectory($manifest->code);
            throw $exception;
        }

        return $manifest;
    }

    /** @return list<array{module_code:string,version:string,package_sha256:string,source:string,installed_at:string,updated_at:string}> */
    public function inventory(): array
    {
        return $this->inventory->all();
    }

    private function removeInstalledDirectory(string $moduleCode): void
    {
        if (!preg_match('/^[a-z][a-z0-9._-]{0,79}$/', $moduleCode)) {
            throw new RuntimeException('Refusing to remove an invalid module path.');
        }

        $path = $this->rootPath . '/modules/' . $moduleCode;
        if (!is_dir($path) || is_link($path)) {
            return;
        }

        $this->removeTree($path);
    }

    private function removeTree(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            throw new RuntimeException('Unable to inspect module directory during rollback: ' . $path);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . '/' . $item;
            if (is_dir($child) && !is_link($child)) {
                $this->removeTree($child);
                continue;
            }

            if (!unlink($child)) {
                throw new RuntimeException('Unable to remove module file during rollback: ' . $child);
            }
        }

        if (!rmdir($path)) {
            throw new RuntimeException('Unable to remove module directory during rollback: ' . $path);
        }
    }
}
