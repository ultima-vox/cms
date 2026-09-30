<?php

declare(strict_types=1);

namespace Core\Extension;

use Core\Version;
use RuntimeException;
use ZipArchive;

final class ModulePackageInstaller
{
    private const MAX_FILES = 5000;
    private const MAX_TOTAL_SIZE = 134_217_728; // 128 MiB
    private const MAX_FILE_SIZE = 33_554_432; // 32 MiB
    private const MAX_MANIFEST_SIZE = 65_536; // 64 KiB

    private readonly ModuleManifestReader $manifestReader;

    public function __construct(private readonly string $rootPath)
    {
        $this->manifestReader = new ModuleManifestReader();
    }

    public function install(string $archivePath): ModuleManifest
    {
        (new ModulePackageSignatureVerifier($this->rootPath))->verify($archivePath);

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
            $entries = $this->inspectArchive($zip);
            $manifest = $this->readManifest($zip);
            $this->assertPackageManifest($manifest);
            $this->assertDependenciesAvailable($manifest);

            $modulesRoot = $this->rootPath . '/modules';
            if (!is_dir($modulesRoot) && !mkdir($modulesRoot, 0775, true) && !is_dir($modulesRoot)) {
                throw new RuntimeException('Unable to create modules directory.');
            }

            $target = $modulesRoot . '/' . $manifest->code;
            if (file_exists($target)) {
                throw new RuntimeException(sprintf(
                    'Module "%s" is already installed. Package upgrades require a separate update lifecycle.',
                    $manifest->code,
                ));
            }

            $staging = $modulesRoot . '/.install-' . $manifest->code . '-' . bin2hex(random_bytes(8));
            if (!mkdir($staging, 0775, false)) {
                throw new RuntimeException('Unable to create module staging directory.');
            }

            try {
                $this->extractVerified($zip, $entries, $staging);

                $stagedManifest = $this->manifestReader->read($staging);
                if (!$stagedManifest instanceof ModuleManifest || $stagedManifest->code !== $manifest->code) {
                    throw new RuntimeException('Staged module manifest does not match the validated package.');
                }

                if (!rename($staging, $target)) {
                    throw new RuntimeException('Unable to atomically move the verified module into modules/.');
                }
            } catch (\Throwable $exception) {
                $this->removeTree($staging);
                throw $exception;
            }

            return ModuleManifest::fromArray([
                'code' => $manifest->code,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'extension_api' => $manifest->extensionApi,
                'default_enabled' => $manifest->defaultEnabled,
                'requires' => $manifest->requires,
                'provider' => $manifest->provider,
            ], $target);
        } finally {
            $zip->close();
        }
    }

    /** @return list<array{index:int,name:string,is_dir:bool,size:int}> */
    private function inspectArchive(ZipArchive $zip): array
    {
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_FILES) {
            throw new RuntimeException(sprintf(
                'Module package must contain between 1 and %d entries.',
                self::MAX_FILES,
            ));
        }

        $entries = [];
        $seen = [];
        $seenPortable = [];
        $totalSize = 0;
        $hasManifest = false;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
            if (!is_array($stat) || !isset($stat['name'])) {
                throw new RuntimeException('Unable to inspect ZIP entry.');
            }

            $name = (string) $stat['name'];
            $normalized = $this->validateEntryPath($name);
            $isDir = str_ends_with($normalized, '/');
            $size = (int) ($stat['size'] ?? 0);

            if ($size < 0 || (!$isDir && $size > self::MAX_FILE_SIZE)) {
                throw new RuntimeException('Module package contains an oversized file: ' . $normalized);
            }

            $totalSize += $size;
            if ($totalSize > self::MAX_TOTAL_SIZE) {
                throw new RuntimeException('Module package exceeds the maximum uncompressed size.');
            }

            if (isset($seen[$normalized])) {
                throw new RuntimeException('Module package contains duplicate entry: ' . $normalized);
            }
            $seen[$normalized] = true;

            $portable = strtolower(rtrim($normalized, '/'));
            if ($portable !== '' && isset($seenPortable[$portable])) {
                throw new RuntimeException('Module package contains a case-conflicting path: ' . $normalized);
            }
            if ($portable !== '') {
                $seenPortable[$portable] = true;
            }

            $this->assertRegularEntry($zip, $index, $normalized, $isDir);

            if ($normalized === 'module.json') {
                if ($isDir || $size > self::MAX_MANIFEST_SIZE) {
                    throw new RuntimeException('module.json is invalid or too large.');
                }
                $hasManifest = true;
            }

            $entries[] = [
                'index' => $index,
                'name' => $normalized,
                'is_dir' => $isDir,
                'size' => $size,
            ];
        }

        if (!$hasManifest) {
            throw new RuntimeException('Installable module package must contain module.json at the archive root.');
        }

        return $entries;
    }

    private function readManifest(ZipArchive $zip): ModuleManifest
    {
        $index = $zip->locateName('module.json', ZipArchive::FL_UNCHANGED);
        if ($index === false) {
            throw new RuntimeException('Module package manifest is missing.');
        }

        $content = $zip->getFromIndex($index, self::MAX_MANIFEST_SIZE, ZipArchive::FL_UNCHANGED);
        if (!is_string($content)) {
            throw new RuntimeException('Unable to read module.json from package.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'uvcms-manifest-');
        if ($temp === false) {
            throw new RuntimeException('Unable to create temporary manifest file.');
        }

        try {
            if (file_put_contents($temp, $content, LOCK_EX) === false) {
                throw new RuntimeException('Unable to stage module manifest for validation.');
            }
            return $this->manifestReader->readJson($temp, $this->rootPath . '/modules/.package');
        } finally {
            @unlink($temp);
        }
    }

    private function assertPackageManifest(ModuleManifest $manifest): void
    {
        if ($manifest->defaultEnabled) {
            throw new RuntimeException(
                'Installable module packages must declare default_enabled=false and require explicit activation.'
            );
        }
        if (!VersionConstraint::matches(ExtensionApiVersion::VERSION, $manifest->extensionApi)) {
            throw new RuntimeException(sprintf(
                'Module "%s" requires Extension API %s, current version is %s.',
                $manifest->code,
                $manifest->extensionApi,
                ExtensionApiVersion::VERSION,
            ));
        }

        $coreConstraint = $manifest->requires['core'] ?? null;
        if (!is_string($coreConstraint) || !VersionConstraint::matches(Version::STRING, $coreConstraint)) {
            throw new RuntimeException(sprintf(
                'Module "%s" is not compatible with Core %s.',
                $manifest->code,
                Version::STRING,
            ));
        }
    }

    private function assertDependenciesAvailable(ModuleManifest $manifest): void
    {
        $installed = [];
        foreach ((new ModuleLoader($this->rootPath))->discover() as $candidate) {
            $installed[$candidate->code] = $candidate;
        }

        foreach ($manifest->requires as $dependency => $constraint) {
            if ($dependency === 'core') {
                continue;
            }

            $required = $installed[$dependency] ?? null;
            if (!$required instanceof ModuleManifest) {
                throw new RuntimeException(sprintf(
                    'Module "%s" requires missing module "%s".',
                    $manifest->code,
                    $dependency,
                ));
            }
            if (!VersionConstraint::matches($required->version, $constraint)) {
                throw new RuntimeException(sprintf(
                    'Module "%s" requires %s %s, installed version is %s.',
                    $manifest->code,
                    $dependency,
                    $constraint,
                    $required->version,
                ));
            }
        }
    }

    /** @param list<array{index:int,name:string,is_dir:bool,size:int}> $entries */
    private function extractVerified(ZipArchive $zip, array $entries, string $staging): void
    {
        $actualTotalSize = 0;

        foreach ($entries as $entry) {
            $relative = rtrim($entry['name'], '/');
            if ($relative === '') {
                continue;
            }

            $destination = $staging . '/' . $relative;
            if ($entry['is_dir']) {
                if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
                    throw new RuntimeException('Unable to create package directory: ' . $relative);
                }
                continue;
            }

            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new RuntimeException('Unable to create package directory: ' . dirname($relative));
            }

            $source = $zip->getStream($entry['name']);
            if (!is_resource($source)) {
                throw new RuntimeException('Unable to read package entry: ' . $relative);
            }
            $target = fopen($destination, 'xb');
            if (!is_resource($target)) {
                fclose($source);
                throw new RuntimeException('Unable to create staged package file: ' . $relative);
            }

            try {
                $copied = stream_copy_to_stream($source, $target, self::MAX_FILE_SIZE + 1);
                if ($copied === false || $copied !== $entry['size']) {
                    throw new RuntimeException('Package entry size changed while extracting: ' . $relative);
                }

                $actualTotalSize += $copied;
                if ($actualTotalSize > self::MAX_TOTAL_SIZE) {
                    throw new RuntimeException('Module package exceeds the maximum actual extracted size.');
                }
            } finally {
                fclose($source);
                fclose($target);
            }
        }
    }

    private function validateEntryPath(string $name): string
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')) {
            throw new RuntimeException('Module package contains an invalid archive path.');
        }
        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) === 1) {
            throw new RuntimeException('Module package contains an absolute archive path: ' . $name);
        }
        if (strlen($name) > 1024) {
            throw new RuntimeException('Module package contains an excessively long path.');
        }

        $parts = explode('/', rtrim($name, '/'));
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new RuntimeException('Module package contains path traversal or ambiguous path: ' . $name);
            }
        }

        return str_ends_with($name, '/') ? implode('/', $parts) . '/' : implode('/', $parts);
    }

    private function assertRegularEntry(ZipArchive $zip, int $index, string $name, bool $isDir): void
    {
        $opsys = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes, ZipArchive::FL_UNCHANGED)) {
            return;
        }

        if ($opsys !== ZipArchive::OPSYS_UNIX) {
            return;
        }

        $mode = ($attributes >> 16) & 0xFFFF;
        $type = $mode & 0170000;
        if ($type === 0) {
            return;
        }
        if ($isDir && $type === 0040000) {
            return;
        }
        if (!$isDir && $type === 0100000) {
            return;
        }

        throw new RuntimeException('Module package contains a symlink or special filesystem entry: ' . $name);
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $child = $path . '/' . $item;
            if (is_dir($child) && !is_link($child)) {
                $this->removeTree($child);
            } else {
                @unlink($child);
            }
        }
        @rmdir($path);
    }
}
