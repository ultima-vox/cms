<?php

declare(strict_types=1);

namespace Core\Extension;

use RuntimeException;

final class ModuleLoader
{
    /** @var list<string> */
    private array $moduleRoots;

    private readonly ModuleManifestReader $manifestReader;

    public function __construct(string $rootPath)
    {
        $this->moduleRoots = [$rootPath . '/modules'];
        $this->manifestReader = new ModuleManifestReader();
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
                $manifest = $this->manifestReader->read($directory);
                if (!$manifest instanceof ModuleManifest) {
                    continue;
                }
                if (isset($manifests[$manifest->code])) {
                    throw new RuntimeException(sprintf('Duplicate module code: %s.', $manifest->code));
                }
                $manifests[$manifest->code] = $manifest;
            }
        }

        ksort($manifests, SORT_STRING);
        return array_values($manifests);
    }

    /** @return list<string> Loaded module codes. */
    public function load(Core $core): array
    {
        $manifests = $this->discover();
        $state = new ModuleStateRepository($core->runtime()->database());
        $ordered = $this->enabledInLoadOrder($manifests, $state);
        $loaded = [];

        foreach ($ordered as $manifest) {
            $autoload = $manifest->directory . '/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }

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

    /**
     * @param list<ModuleManifest> $manifests
     * @return list<ModuleManifest>
     */
    public function enabledInLoadOrder(array $manifests, ModuleStateRepository $state): array
    {
        $all = $this->index($manifests);
        $enabled = [];
        foreach ($all as $code => $manifest) {
            if ($state->isEnabled($manifest)) {
                $enabled[$code] = $manifest;
            }
        }

        foreach ($enabled as $manifest) {
            $this->assertExtensionApiCompatible($manifest);
            $this->assertDependenciesSatisfied($manifest, $all, $state, true);
        }

        return $this->topologicalSort($enabled);
    }

    /** @param list<ModuleManifest> $manifests */
    public function assertCanEnable(string $code, array $manifests, ModuleStateRepository $state): void
    {
        $all = $this->index($manifests);
        $manifest = $all[$code] ?? throw new RuntimeException(sprintf('Unknown module: %s.', $code));
        $this->assertExtensionApiCompatible($manifest);
        $this->assertDependenciesSatisfied($manifest, $all, $state, true);
    }

    /** @param list<ModuleManifest> $manifests */
    public function assertCanDisable(string $code, array $manifests, ModuleStateRepository $state): void
    {
        $all = $this->index($manifests);
        if (!isset($all[$code])) {
            throw new RuntimeException(sprintf('Unknown module: %s.', $code));
        }

        foreach ($all as $candidate) {
            if ($candidate->code === $code || !$state->isEnabled($candidate)) {
                continue;
            }
            if (array_key_exists($code, $candidate->requires)) {
                throw new RuntimeException(sprintf(
                    'Cannot disable "%s": enabled module "%s" depends on it.',
                    $code,
                    $candidate->code,
                ));
            }
        }
    }

    private function assertExtensionApiCompatible(ModuleManifest $manifest): void
    {
        if (!VersionConstraint::matches(ExtensionApiVersion::VERSION, $manifest->extensionApi)) {
            throw new RuntimeException(sprintf(
                'Module "%s" requires Extension API %s, current version is %s.',
                $manifest->code,
                $manifest->extensionApi,
                ExtensionApiVersion::VERSION,
            ));
        }
    }

    /** @param array<string, ModuleManifest> $all */
    private function assertDependenciesSatisfied(
        ModuleManifest $manifest,
        array $all,
        ModuleStateRepository $state,
        bool $requireEnabled,
    ): void {
        foreach ($manifest->requires as $dependency => $constraint) {
            if ($dependency === 'core') {
                if (!VersionConstraint::matches(\Core\Version::STRING, $constraint)) {
                    throw new RuntimeException(sprintf(
                        'Module "%s" requires core %s, current version is %s.',
                        $manifest->code,
                        $constraint,
                        \Core\Version::STRING,
                    ));
                }
                continue;
            }

            $required = $all[$dependency] ?? null;
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
            if ($requireEnabled && !$state->isEnabled($required)) {
                throw new RuntimeException(sprintf(
                    'Module "%s" requires enabled module "%s" (%s).',
                    $manifest->code,
                    $dependency,
                    $constraint,
                ));
            }
        }
    }

    /** @param array<string, ModuleManifest> $manifests @return list<ModuleManifest> */
    private function topologicalSort(array $manifests): array
    {
        $state = [];
        $result = [];

        $visit = function (string $code) use (&$visit, &$state, &$result, $manifests): void {
            $mark = $state[$code] ?? 0;
            if ($mark === 2) {
                return;
            }
            if ($mark === 1) {
                throw new RuntimeException(sprintf('Circular module dependency detected at %s.', $code));
            }

            $state[$code] = 1;
            $manifest = $manifests[$code];
            foreach (array_keys($manifest->requires) as $dependency) {
                if ($dependency !== 'core' && isset($manifests[$dependency])) {
                    $visit($dependency);
                }
            }
            $state[$code] = 2;
            $result[] = $manifest;
        };

        foreach (array_keys($manifests) as $code) {
            $visit($code);
        }

        return $result;
    }

    /** @param list<ModuleManifest> $manifests @return array<string, ModuleManifest> */
    private function index(array $manifests): array
    {
        $result = [];
        foreach ($manifests as $manifest) {
            $result[$manifest->code] = $manifest;
        }
        return $result;
    }
}
