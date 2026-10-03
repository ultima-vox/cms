<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu;

use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\View\Render\TemplateFacadeContext;
use UltimaVox\Modules\Menu\Repository\MenuRepository;

final readonly class MenuModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $db = $core->runtime()->database();
        $repository = new MenuRepository($db);
        $extensions = $core->extensions();

        $extensions->register(
            NavigationSourceInterface::EXTENSION_POINT,
            'structure.children',
            new StructureNavigationSource($db),
        );

        $core->templates()->view(
            'menu.default',
            'menu.tree',
            'modules/menu/templates/default.php',
        );

        $core->templates()->facade(
            'menus',
            static fn (TemplateFacadeContext $context): MenusFacade => new MenusFacade(
                $context,
                $repository,
                $extensions,
            ),
        );
    }
}
