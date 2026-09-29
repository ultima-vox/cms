<?php

declare(strict_types=1);

namespace Core\Extension;

use Core\Bootstrap\BuiltinExtensions;
use Core\Bootstrap\RuntimeFactory;
use PDO;
use RuntimeException;

final class ModuleManager
{
    private ModuleCatalog $catalog;
    private ModuleStateRepository $state;

    public function __construct(
        private readonly PDO $db,
        private readonly string $rootPath,
    ) {
        $this->catalog = new ModuleCatalog($rootPath);
        $this->state = new ModuleStateRepository($db);
    }

    /** @return array{modules:int,permissions:int} */
    public function sync(): array
    {
        $manifests = $this->catalog->discover();
        $modules = $this->state->sync($manifests);
        $runtime = (new RuntimeFactory())->create($this->db, $this->rootPath);
        $core = new Core($runtime);
        (new BuiltinExtensions())->register($core);
        (new ModuleLoader($this->rootPath))->load($core);
        $core->freeze();
        $permissions = (new PermissionSynchronizer($this->db))->sync($core->permissions());

        return [
            'modules' => $modules,
            'permissions' => $permissions,
        ];
    }

    /** @return list<array{code:string,name:string,version:string,enabled:bool,synchronized:bool}> */
    public function listing(): array
    {
        $manifests = $this->catalog->discover();
        $stored = $this->state->all();
        $rows = [];

        foreach ($manifests as $manifest) {
            $record = $stored[$manifest->code] ?? null;
            $rows[] = [
                'code' => $manifest->code,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'enabled' => $record === null ? $manifest->defaultEnabled : $record['is_enabled'],
                'synchronized' => $record !== null,
            ];
        }

        return $rows;
    }

    public function enable(string $code): void
    {
        $manifests = $this->catalog->discover();
        $this->state->sync($manifests);
        $this->catalog->assertCanEnable($code, $manifests, $this->state);
        $this->state->setEnabled($code, true);
    }

    public function disable(string $code): void
    {
        $manifests = $this->catalog->discover();
        $this->state->sync($manifests);
        $this->catalog->assertCanDisable($code, $manifests, $this->state);
        $this->state->setEnabled($code, false);
    }

    public function requireManifest(string $code): ModuleManifest
    {
        $manifest = $this->catalog->discover()[$code] ?? null;
        if (!$manifest instanceof ModuleManifest) {
            throw new RuntimeException(sprintf('Unknown module: %s.', $code));
        }

        return $manifest;
    }
}
