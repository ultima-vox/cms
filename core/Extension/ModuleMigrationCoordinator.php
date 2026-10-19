<?php

declare(strict_types=1);

namespace Core\Extension;

use PDO;
use RuntimeException;

final readonly class ModuleMigrationCoordinator
{
    public function __construct(
        private PDO $db,
        private string $rootPath,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function migrateEnabled(): array
    {
        $loader = new ModuleLoader($this->rootPath);
        $manifests = $loader->discover();
        $state = new ModuleStateRepository($this->db);
        $state->sync($manifests);

        $enabled = array_filter($manifests, static fn ($m) => $state->isEnabled($m));
        $build = json_decode((string) @file_get_contents($this->rootPath . '/release.json'), true);
        $targetCore = is_string($build['core_version'] ?? null) ? $build['core_version'] : \Core\Version::STRING;
        $indexed = [];
        foreach ($manifests as $manifest) $indexed[$manifest->code] = $manifest;
        foreach ($enabled as $manifest) {
            if (!VersionConstraint::matches(ExtensionApiVersion::VERSION, $manifest->extensionApi)) throw new RuntimeException('Incompatible Extension API: ' . $manifest->code);
            foreach ($manifest->requires as $code => $constraint) {
                $version = $code === 'core' ? $targetCore : ($indexed[$code]->version ?? '');
                if ($version === '' || !VersionConstraint::matches($version, $constraint)
                    || ($code !== 'core' && !$state->isEnabled($indexed[$code]))) throw new RuntimeException('Unsatisfied migration dependency: ' . $manifest->code . ' requires ' . $code);
            }
        }
        $ordered = $this->migrationOrder(array_values($enabled));
        $runner = new ModuleMigrationRunner($this->db, $this->rootPath);
        $result = [];

        foreach ($ordered as $manifest) {
            $applied = $runner->migrate($manifest->code);
            if ($applied !== []) {
                $result[$manifest->code] = $applied;
            }
        }

        return $result;
    }

    /** @param list<ModuleManifest> $manifests @return list<ModuleManifest> */
    private function migrationOrder(array $manifests): array
    {
        $enabled = [];
        foreach ($manifests as $manifest) {
            $enabled[$manifest->code] = $manifest;
        }
        $marks = [];
        $ordered = [];
        $visit = function (string $code) use (&$visit, &$marks, &$ordered, $enabled): void {
            if (($marks[$code] ?? 0) === 2) {
                return;
            }
            if (($marks[$code] ?? 0) === 1) {
                throw new RuntimeException('Circular module migration dependency detected at ' . $code . '.');
            }
            $marks[$code] = 1;
            $manifest = $enabled[$code];
            $dependencies = array_unique(array_merge(array_keys($manifest->requires), $manifest->migrationAfter));
            sort($dependencies, SORT_STRING);
            foreach ($dependencies as $dependency) {
                // Optional migration ordering never enables or installs another module.
                if (isset($enabled[$dependency])) {
                    $visit($dependency);
                }
            }
            $marks[$code] = 2;
            $ordered[] = $manifest;
        };
        foreach (array_keys($enabled) as $code) {
            $visit($code);
        }
        return $ordered;
    }

}
