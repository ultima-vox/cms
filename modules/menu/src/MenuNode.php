<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu;

final readonly class MenuNode
{
    /** @param list<MenuNode> $children */
    public function __construct(
        public int $id,
        public string $label,
        public string $url,
        public bool $current,
        public bool $active,
        public array $children = [],
    ) {
    }
}
