<?php

declare(strict_types=1);

namespace Core\Extension;

use Core\Version;
use RuntimeException;

final class ModuleLoader
{
    /** @var list<string> */
    private array $moduleRoots;

    public function __construct(string $rootPath)
    {
        $this->moduleRoots = [$rootPath . '/modules'];
    }

    /** @return list<ModuleManifest> */
    public function discover(): array
    {
        $manifests = [];

        foreach ($this->moduleRoots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $directories = glob($root . '/*', GLOB_ONLYDIR) ?: [];
            sort($directories, SORT_STRING);

            foreach ($directories as $directory) {
                $manifestPath = $directory . '/module.php';
                if (!is_file($manifestPath)) {
                    continue;
                }

                $definition = require $manifestPath;
                if ($definition instanceof ModuleInterface) {
                    $definition = [
                        'code' => basename($directory),
                        'name' => basename($directory),
                        'version' => '0.0.0',
                        'requires' => ['core' => '>=' . Version::STRING],
                        'provider' => $definition::class,
                    ];
                }
                if (!is_array($definition)) {
                    throw new RuntimeException(sprintf('Module manifest %s must return an array.', $manifestPath));
                }

                $manifest = ModuleManifest::fromArray($definition, $directory);
                if (isset($manifests[$manifest->code])) {
                    throw new RuntimeException(sprintf('Duplicate module code: %s.', $manifest->code));
                }
                $manifests[$manifest->code] = $manifest;
            }
        }

        return $this->sortAndValidate($manifests);
    }

    /** @return list<string> Loaded module codes. */
    public function load(Core $core): array
    {
        $loaded = [];

        foreach ($this->discover() as $manifest) {
            if (!class_exists($manifest->provider)) {
                throw new RuntimeException(sprintf(
                    'Module %s provider class %s was not found.',
                    $manifest->code,
                    $manifest->provider,
                ));
            }

            $module = new ($manifest->provider)();
            if (!$module instanceof ModuleInterface) {
                throw new RuntimeException(sprintf(
                    'Module %s provider must implement ModuleInterface.',
                    $manifest->code,
                ));
            }

            $module->register($core);
            $loaded[] = $manifest->code;
        }

        return $loaded;
    }

    /** @param array<string, ModuleManifest> $manifests @return list<ModuleManifest> */
    private function sortAndValidate(array $manifests): array
    {
        $sorted = [];
        $state = [];

        $visit = function (string $code) use (&$visit, &$sorted, &$state, $manifests): void {
            if (($state[$code] ?? 0) === 2) {
                return;
            }
            if (($state[$code] ?? 0) === 1) {
                throw new RuntimeException(sprintf('Circular module dependency detected at %s.', $code));
            }

            $manifest = $manifests[$code] ?? throw new RuntimeException(sprintf('Unknown module %s.', $code));
            $state[$code] = 1;

            foreach ($manifest->requires as $dependency => $constraint) {
                if ($dependency === 'core') {
                    $this->assertVersion($manifest->code, 'core', Version::STRING, $constraint);
                    continue;
                }

                $dependencyManifest = $manifests[$dependency] ?? throw new RuntimeException(sprintf(
                    'Module %s requires missing module %s (%s).',
                    $manifest->code,
                    $dependency,
                    $constraint,
                ));
                $this->assertVersion($manifest->code, $dependency, $dependencyManifest->version, $constraint);
                $visit($dependency);
            }

            $state[$code] = 2;
            $sorted[] = $manifest;
        };

        foreach (array_keys($manifests) as $code) {
            $visit($code);
        }

        return $sorted;
    }

    private function assertVersion(string $module, string $dependency, string $version, string $constraint): void
    {
        $constraint = trim($constraint);
        if ($constraint === '*') {
            return;
        }

        if (preg_match('/^(>=|<=|>|<|=)?\s*(\d+\.\d+\.\d+)$/', $constraint, $matches) !== 1) {
            throw new RuntimeException(sprintf(
                'Module %s uses unsupported version constraint "%s" for %s.',
                $module,
                $constraint,
                $dependency,
            ));
        }

        $operator = $matches[1] !== '' ? $matches[1] : '=';
        if (!version_compare($version, $matches[2], $operator)) {
            throw new RuntimeException(sprintf(
                'Module %s requires %s %s, installed version is %s.',
                $module,
                $dependency,
                $constraint,
                $version,
            ));
        }
    }
}
