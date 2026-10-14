<?php

declare(strict_types=1);

namespace Core\Extension;

use PDO;

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

        $ordered = $loader->enabledInLoadOrder($manifests, $state);
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
}
