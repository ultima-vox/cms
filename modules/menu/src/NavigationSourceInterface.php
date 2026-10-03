<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu;

interface NavigationSourceInterface
{
    public const EXTENSION_POINT = 'menu.navigation-source';

    /** @param array<string, mixed> $configuration @return list<NavigationNode> */
    public function resolve(NavigationSourceContext $context, array $configuration): array;
}
