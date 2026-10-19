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
        // The updating request still holds the previous PHP classes and Composer class map.
        // Reconcile providers and permissions only in a fresh worker after code replacement.
        if (is_file($this->rootPath . '/storage/updates/maintenance.json')) {
            $this->db->exec('INSERT INTO extension_sync_jobs(singleton) VALUES(TRUE)' . \Core\Database\SqlDialect::upsert($this->db, ['singleton'], ['requested_at']));
            return ['modules' => $modules, 'loaded' => 0, 'permissions' => 0];
        }

        $core = new Core(new RuntimeApi($this->db, $this->rootPath));
        (new BuiltinExtensions())->register($core);
        $loaded = $this->loader->load($core);
        $core->freeze();
        $permissions = (new PermissionSynchronizer($this->db))->sync($core->permissions());
        $this->db->exec("UPDATE permissions SET global_only=TRUE WHERE code IN ('templates.code.edit','modules.manage')");
        $this->db->exec("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE global_only = TRUE) AND role_id IN (SELECT id FROM roles WHERE code <> 'superadmin')");

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
        \Core\Database\SqlDialect::migrationPath($this->db, $this->rootPath . '/modules/' . $code . '/migrations');
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
