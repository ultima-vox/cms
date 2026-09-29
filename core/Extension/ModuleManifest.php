<?php

declare(strict_types=1);

namespace Core\Extension;

use RuntimeException;
use Throwable;

final readonly class ModuleManifest
{
    /** @param array<string, string> $requires */
    private function __construct(
        public string $code,
        public string $name,
        public string $version,
        public string $extensionApi,
        public string $directory,
        public string $bootstrap,
        public ?string $migrations,
        public bool $defaultEnabled,
        public array $requires,
    ) {
    }

    public static function fromFile(string $file): self
    {
        $realFile = realpath($file);
        if ($realFile === false) {
            throw new RuntimeException(sprintf('Unable to resolve module manifest: %s.', $file));
        }

        $json = file_get_contents($realFile);
        if ($json === false) {
            throw new RuntimeException(sprintf('Unable to read module manifest: %s.', $realFile));
        }

        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('Invalid JSON module manifest: %s.', $realFile), 0, $exception);
        }

        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Module manifest must contain a JSON object: %s.', $realFile));
        }

        $directory = realpath(dirname($realFile));
        if ($directory === false) {
            throw new RuntimeException(sprintf('Unable to resolve module directory: %s.', dirname($realFile)));
        }

        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $version = trim((string) ($data['version'] ?? ''));
        $extensionApi = trim((string) ($data['extension_api'] ?? ''));
        $bootstrap = trim((string) ($data['bootstrap'] ?? 'module.php'));
        $migrations = isset($data['migrations']) ? trim((string) $data['migrations']) : null;
        $defaultEnabled = (bool) ($data['default_enabled'] ?? false);
        $requires = $data['requires'] ?? [];

        if (!preg_match('/^[a-z][a-z0-9._-]{0,79}$/', $code)) {
            throw new RuntimeException(sprintf('Module code is invalid in %s.', $realFile));
        }
        if ($name === '' || preg_match_all('/./u', $name) > 120) {
            throw new RuntimeException(sprintf('Module name is invalid in %s.', $realFile));
        }

        try {
            VersionConstraint::assertVersion($version);
            VersionConstraint::assertConstraint($extensionApi);
        } catch (RuntimeException $exception) {
            throw new RuntimeException(sprintf('Module version or extension_api constraint is invalid in %s: %s', $realFile, $exception->getMessage()), 0, $exception);
        }

        self::assertRelativePhpFile($bootstrap, 'bootstrap', $realFile);
        if ($migrations !== null && $migrations !== '') {
            self::assertRelativePath($migrations, 'migrations', $realFile);
        } else {
            $migrations = null;
        }

        if (!is_array($requires)) {
            throw new RuntimeException(sprintf('Module requires must be an object in %s.', $realFile));
        }

        $normalizedRequires = [];
        foreach ($requires as $requiredCode => $constraint) {
            $requiredCode = trim((string) $requiredCode);
            $constraint = trim((string) $constraint);

            if (!preg_match('/^[a-z][a-z0-9._-]{0,79}$/', $requiredCode) || $requiredCode === $code) {
                throw new RuntimeException(sprintf('Invalid module dependency in %s.', $realFile));
            }

            try {
                VersionConstraint::assertConstraint($constraint);
            } catch (RuntimeException $exception) {
                throw new RuntimeException(sprintf('Invalid dependency constraint for %s in %s.', $requiredCode, $realFile), 0, $exception);
            }

            $normalizedRequires[$requiredCode] = $constraint;
        }
        ksort($normalizedRequires, SORT_STRING);

        $manifest = new self(
            $code,
            $name,
            $version,
            $extensionApi,
            $directory,
            $bootstrap,
            $migrations,
            $defaultEnabled,
            $normalizedRequires,
        );

        $manifest->resolveContainedPath($manifest->bootstrapPath(), true);
        if ($manifest->migrationsPath() !== null && file_exists($manifest->migrationsPath())) {
            $manifest->resolveContainedPath($manifest->migrationsPath(), false);
        }

        return $manifest;
    }

    public function bootstrapPath(): string
    {
        return $this->directory . '/' . $this->bootstrap;
    }

    public function migrationsPath(): ?string
    {
        return $this->migrations === null ? null : $this->directory . '/' . $this->migrations;
    }

    private function resolveContainedPath(string $path, bool $mustBeFile): string
    {
        $real = realpath($path);
        if ($real === false
            || !str_starts_with($real, $this->directory . DIRECTORY_SEPARATOR)
            || ($mustBeFile && !is_file($real))) {
            throw new RuntimeException(sprintf('Module path escapes its package directory: %s.', $path));
        }

        return $real;
    }

    private static function assertRelativePhpFile(string $path, string $field, string $file): void
    {
        self::assertRelativePath($path, $field, $file);
        if (!str_ends_with($path, '.php')) {
            throw new RuntimeException(sprintf('Module %s must reference a PHP file in %s.', $field, $file));
        }
    }

    private static function assertRelativePath(string $path, string $field, string $file): void
    {
        if ($path === ''
            || str_starts_with($path, '/')
            || str_contains($path, '..')
            || str_contains($path, "\\")
            || preg_match('/[\r\n\0]/', $path)) {
            throw new RuntimeException(sprintf('Module %s path is invalid in %s.', $field, $file));
        }
    }
}
