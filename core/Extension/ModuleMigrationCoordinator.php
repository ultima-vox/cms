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

        $ordered = $this->migrationOrder($loader->enabledInLoadOrder($manifests, $state));
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
