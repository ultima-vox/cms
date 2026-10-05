<?php

declare(strict_types=1);

namespace UltimaVox\Modules\MenuInfosystem;

use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use UltimaVox\Modules\Infosystem\Repository\InfosystemRepository;
use UltimaVox\Modules\Menu\NavigationSourceInterface;

final readonly class MenuInfosystemModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $db = $core->runtime()->database();

        $core->extensions()->register(
            NavigationSourceInterface::EXTENSION_POINT,
            'infosystem.navigation',
            new InfosystemNavigationSource(
                $db,
                new InfosystemRepository($db),
            ),
        );
    }
}
