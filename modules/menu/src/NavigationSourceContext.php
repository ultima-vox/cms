<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu;

use Core\Site\SiteContext;
use Core\View\Render\RenderContext;

final readonly class NavigationSourceContext
{
    /** @param array<string, mixed> $currentNode */
    public function __construct(
        public SiteContext $site,
        public RenderContext $renderContext,
        public array $currentNode,
        public int $menuId,
        public string $menuCode,
    ) {
    }
}
