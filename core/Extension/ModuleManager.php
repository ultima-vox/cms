<?php

declare(strict_types=1);

namespace Core\Extension;

use Core\Bootstrap\BuiltinExtensions;
use Core\Extension\Api\RuntimeApi;
use PDO;
use RuntimeException;

final class ModuleManager
{
    private ModuleLoader $loader;
    private ModuleStateRepository $state;

    public function __construct(
        private readonly PDO $db,
        private readonly string $rootPath,
    ) {
        $this->loader = new ModuleLoader($rootPath);
        $this->state = new ModuleStateRepository($db);
    }

    /** @return array{modules:int,loaded:int,permissions:int} */
    public function sync(): array
    {
        $manifests = $this->loader->discover();
        $modules = $this->state->sync($manifests);

        $core = new Core(new RuntimeApi($this->db, $this->rootPath));
        (new BuiltinExtensions())->register($core);
        $loaded = $this->loader->load($core);
        $core->freeze();
        $permissions = (new PermissionSynchronizer($this->db))->sync($core->permissions());

        return [
            'modules' => $modules,
            'loaded' => count($loaded),
            'permissions' => $permissions,
        ];
    }

    /** @return list<array{code:string,name:string,version:string,extension_api:string,enabled:bool,synchronized:bool}> */
    public function listing(): array
    {
        $manifests = $this->loader->discover();
        $stored = $this->state->all();
        $rows = [];

        foreach ($manifests as $manifest) {
            $record = $stored[$manifest->code] ?? null;
            $rows[] = [
                'code' => $manifest->code,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'extension_api' => $manifest->extensionApi,
                'enabled' => $record === null ? $manifest->defaultEnabled : $record['is_enabled'],
                'synchronized' => $record !== null,
            ];
        }

        return $rows;
    }

    public function enable(string $code): void
    {
        $manifests = $this->loader->discover();
        $this->state->sync($manifests);
        $this->loader->assertCanEnable($code, $manifests, $this->state);
        $this->state->setEnabled($code, true);
    }

    public function disable(string $code): void
    {
        $manifests = $this->loader->discover();
        $this->state->sync($manifests);
        $this->loader->assertCanDisable($code, $manifests, $this->state);
        $this->state->setEnabled($code, false);
    }

    public function requireManifest(string $code): ModuleManifest
    {
        foreach ($this->loader->discover() as $manifest) {
            if ($manifest->code === $code) {
                return $manifest;
            }
        }

        throw new RuntimeException(sprintf('Unknown module: %s.', $code));
    }
}
