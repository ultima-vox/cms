<?php

declare(strict_types=1);

namespace Core\Extension;

use RuntimeException;

final class ModuleCatalog
{
    public function __construct(private readonly string $rootPath)
    {
    }

    /** @return array<string, ModuleManifest> */
    public function discover(): array
    {
        $root = $this->rootPath . '/modules';
        if (!is_dir($root)) {
            return [];
        }

        $files = glob($root . '/*/module.json') ?: [];
        sort($files, SORT_STRING);

        $manifests = [];
        foreach ($files as $file) {
            $manifest = ModuleManifest::fromFile($file);
            if (isset($manifests[$manifest->code])) {
                throw new RuntimeException(sprintf('Duplicate module code "%s".', $manifest->code));
            }
            $manifests[$manifest->code] = $manifest;
        }

        ksort($manifests, SORT_STRING);

        return $manifests;
    }

    /**
     * @param array<string, ModuleManifest> $manifests
     * @return list<ModuleManifest>
     */
    public function enabledInLoadOrder(array $manifests, ModuleStateRepository $state): array
    {
        $enabled = [];
        foreach ($manifests as $code => $manifest) {
            if ($state->isEnabled($manifest)) {
                $enabled[$code] = $manifest;
            }
        }

        foreach ($enabled as $manifest) {
            $this->assertExtensionApiCompatible($manifest);
            $this->assertDependenciesSatisfied($manifest, $manifests, $state, true);
        }

        return $this->topologicalSort($enabled);
    }

    /** @param array<string, ModuleManifest> $manifests */
    public function assertCanEnable(string $code, array $manifests, ModuleStateRepository $state): void
    {
        $manifest = $manifests[$code] ?? throw new RuntimeException(sprintf('Unknown module: %s.', $code));
        $this->assertExtensionApiCompatible($manifest);
        $this->assertDependenciesSatisfied($manifest, $manifests, $state, true);
    }

    /** @param array<string, ModuleManifest> $manifests */
    public function assertCanDisable(string $code, array $manifests, ModuleStateRepository $state): void
    {
        if (!isset($manifests[$code])) {
            throw new RuntimeException(sprintf('Unknown module: %s.', $code));
        }

        foreach ($manifests as $candidate) {
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

    /** @param array<string, ModuleManifest> $manifests */
    private function assertDependenciesSatisfied(
        ModuleManifest $manifest,
        array $manifests,
        ModuleStateRepository $state,
        bool $requireEnabled,
    ): void {
        foreach ($manifest->requires as $requiredCode => $constraint) {
            $required = $manifests[$requiredCode] ?? null;
            if (!$required instanceof ModuleManifest) {
                throw new RuntimeException(sprintf(
                    'Module "%s" requires missing module "%s".',
                    $manifest->code,
                    $requiredCode,
                ));
            }
            if (!VersionConstraint::matches($required->version, $constraint)) {
                throw new RuntimeException(sprintf(
                    'Module "%s" requires %s %s, installed version is %s.',
                    $manifest->code,
                    $requiredCode,
                    $constraint,
                    $required->version,
                ));
            }
            if ($requireEnabled && !$state->isEnabled($required)) {
                throw new RuntimeException(sprintf(
                    'Module "%s" requires enabled module "%s" (%s).',
                    $manifest->code,
                    $requiredCode,
                    $constraint,
                ));
            }
        }
    }

    /**
     * @param array<string, ModuleManifest> $manifests
     * @return list<ModuleManifest>
     */
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
                throw new RuntimeException(sprintf('Cyclic module dependency detected at "%s".', $code));
            }

            $state[$code] = 1;
            $manifest = $manifests[$code];
            foreach (array_keys($manifest->requires) as $requiredCode) {
                if (isset($manifests[$requiredCode])) {
                    $visit($requiredCode);
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
}
