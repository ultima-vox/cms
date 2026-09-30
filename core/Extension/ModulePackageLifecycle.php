<?php

declare(strict_types=1);

namespace Core\Extension;

use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

final readonly class ModulePackageLifecycle
{
    private ModulePackageInventoryRepository $inventory;
    private ModuleManifestReader $manifestReader;

    public function __construct(
        private PDO $db,
        private string $rootPath,
    ) {
        $this->inventory = new ModulePackageInventoryRepository($db);
        $this->manifestReader = new ModuleManifestReader();
    }

    public function install(string $archivePath): ModuleManifest
    {
        $checksum = $this->packageChecksum($archivePath);

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

    public function update(string $archivePath): ModuleManifest
    {
        $incoming = $this->inspectPackageManifest($archivePath);
        $current = $this->inventory->find($incoming->code);
        if ($current === null || $current['source'] !== 'package') {
            throw new RuntimeException(sprintf(
                'Module "%s" is not an installer-managed package and cannot be updated with modules:update.',
                $incoming->code,
            ));
        }

        if (!version_compare($incoming->version, $current['version'], '>')) {
            throw new RuntimeException(sprintf(
                'Module update must increase the version: installed %s, package %s.',
                $current['version'],
                $incoming->version,
            ));
        }

        $state = (new ModuleStateRepository($this->db))->all()[$incoming->code] ?? null;
        if (!is_array($state)) {
            throw new RuntimeException(sprintf(
                'Module "%s" is not synchronized. Run extensions:sync before updating it.',
                $incoming->code,
            ));
        }
        if ($state['is_enabled']) {
            throw new RuntimeException(sprintf(
                'Module "%s" must be disabled before package update.',
                $incoming->code,
            ));
        }

        $target = $this->modulePath($incoming->code);
        if (!is_dir($target) || is_link($target)) {
            throw new RuntimeException(sprintf(
                'Installed module directory is missing or invalid: %s.',
                $incoming->code,
            ));
        }

        $checksum = $this->packageChecksum($archivePath);
        $backup = $this->rootPath . '/modules/.update-backup-' . $incoming->code . '-' . bin2hex(random_bytes(8));
        if (!rename($target, $backup)) {
            throw new RuntimeException('Unable to stage the current module version for update rollback.');
        }

        try {
            $installed = (new ModulePackageInstaller($this->rootPath))->install($archivePath);
            if ($installed->code !== $incoming->code || $installed->version !== $incoming->version) {
                throw new RuntimeException('Installed update manifest differs from the validated package manifest.');
            }

            $this->inventory->recordUpdate($installed->code, $installed->version, $checksum);
        } catch (Throwable $exception) {
            if (is_dir($target)) {
                $this->removeTree($target);
            }
            if (!rename($backup, $target)) {
                throw new RuntimeException(
                    'Module update failed and automatic filesystem rollback also failed.',
                    0,
                    $exception,
                );
            }
            throw $exception;
        }

        $this->removeTree($backup);
        return $installed;
    }

    /** @return list<array{module_code:string,version:string,package_sha256:string,source:string,installed_at:string,updated_at:string}> */
    public function inventory(): array
    {
        return $this->inventory->all();
    }

    private function inspectPackageManifest(string $archivePath): ModuleManifest
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP module installation requires the PHP zip extension.');
        }
        if (!is_file($archivePath) || !is_readable($archivePath)) {
            throw new RuntimeException('Module package is not a readable file: ' . $archivePath);
        }

        $zip = new ZipArchive();
        $result = $zip->open($archivePath, ZipArchive::RDONLY);
        if ($result !== true) {
            throw new RuntimeException(sprintf('Unable to open module ZIP package (%s).', (string) $result));
        }

        try {
            $index = $zip->locateName('module.json', ZipArchive::FL_UNCHANGED);
            if ($index === false) {
                throw new RuntimeException('Installable module package must contain module.json at the archive root.');
            }
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
            if (!is_array($stat) || (int) ($stat['size'] ?? 0) > 65_536) {
                throw new RuntimeException('module.json is invalid or too large.');
            }
            $content = $zip->getFromIndex($index, 65_536, ZipArchive::FL_UNCHANGED);
            if (!is_string($content)) {
                throw new RuntimeException('Unable to read module.json from package.');
            }
        } finally {
            $zip->close();
        }

        $temp = tempnam(sys_get_temp_dir(), 'uvcms-update-manifest-');
        if ($temp === false) {
            throw new RuntimeException('Unable to create temporary update manifest file.');
        }

        try {
            if (file_put_contents($temp, $content, LOCK_EX) === false) {
                throw new RuntimeException('Unable to stage update manifest for validation.');
            }
            return $this->manifestReader->readJson($temp, $this->rootPath . '/modules/.package-update');
        } finally {
            @unlink($temp);
        }
    }

    private function packageChecksum(string $archivePath): string
    {
        $checksum = hash_file('sha256', $archivePath);
        if (!is_string($checksum) || strlen($checksum) !== 64) {
            throw new RuntimeException('Unable to calculate module package SHA-256 checksum.');
        }

        return $checksum;
    }

    private function modulePath(string $moduleCode): string
    {
        if (!preg_match('/^[a-z][a-z0-9._-]{0,79}$/', $moduleCode)) {
            throw new RuntimeException('Refusing to use an invalid module path.');
        }

        return $this->rootPath . '/modules/' . $moduleCode;
    }

    private function removeInstalledDirectory(string $moduleCode): void
    {
        $path = $this->modulePath($moduleCode);
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
