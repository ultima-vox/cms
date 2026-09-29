<?php

declare(strict_types=1);

namespace Core\Extension;

use RuntimeException;

final class ModuleLoader
{
    public function __construct(private readonly string $rootPath)
    {
    }

    /** @return list<string> Loaded module codes. */
    public function load(Core $core): array
    {
        $catalog = new ModuleCatalog($this->rootPath);
        $manifests = $catalog->discover();
        $state = new ModuleStateRepository($core->runtime()->database());
        $ordered = $catalog->enabledInLoadOrder($manifests, $state);
        $loaded = [];

        foreach ($ordered as $manifest) {
            $module = require $manifest->bootstrapPath();

            if (is_string($module) && class_exists($module)) {
                $module = new $module();
            }

            if (!$module instanceof ModuleInterface) {
                throw new RuntimeException(sprintf(
                    'Module bootstrap %s must return a ModuleInterface instance or class-string.',
                    $manifest->bootstrapPath(),
                ));
            }

            $module->register($core);
            $loaded[] = $manifest->code;
        }

        return $loaded;
    }
}
