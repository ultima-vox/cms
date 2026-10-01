<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\Repository\NodeModuleBindingRepository;
use UltimaVox\Modules\Infosystem\Repository\InfosystemRepository;

final readonly class InfosystemProvider implements ModuleInterface
{
    public function register(Core $core): void
    {
        (new InfosystemModule())->register($core);

        $db = $core->runtime()->database();
        $core->pages()->executor(
            'infosystem.list',
            new InfosystemPageExecutor(
                new InfosystemRepository($db),
                new NodeModuleBindingRepository($db),
                $core->events(),
                $core->templates(),
                $core->runtime()->rootPath(),
            ),
        );
    }
}
