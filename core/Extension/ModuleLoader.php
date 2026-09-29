<?php

declare(strict_types=1);

namespace Core\Extension;

use RuntimeException;

final class ModuleLoader
{
    /** @var list<string> */
    private array $moduleRoots;

    public function __construct(string $rootPath)
    {
        // Only the dedicated module code directory is auto-executed.
        // Generic writable storage must never become an implicit PHP execution path.
        $this->moduleRoots = [
            $rootPath . '/modules',
        ];
    }

    /** @return list<string> Loaded module directory names. */
    public function load(Core $core): array
    {
        $manifests = [];

        foreach ($this->moduleRoots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $directories = glob($root . '/*', GLOB_ONLYDIR) ?: [];
            sort($directories, SORT_STRING);

            foreach ($directories as $directory) {
                $manifest = $directory . '/module.php';
                if (is_file($manifest)) {
                    $manifests[$manifest] = basename($directory);
                }
            }
        }

        ksort($manifests, SORT_STRING);
        $loaded = [];

        foreach ($manifests as $manifest => $directoryName) {
            $module = require $manifest;

            if (is_string($module) && class_exists($module)) {
                $module = new $module();
            }

            if (!$module instanceof ModuleInterface) {
                throw new RuntimeException(sprintf(
                    'Module manifest %s must return a ModuleInterface instance or class-string.',
                    $manifest,
                ));
            }

            $module->register($core);
            $loaded[] = $directoryName;
        }

        return $loaded;
    }
}
