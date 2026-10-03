<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu;

final readonly class MenuViewModel
{
    /** @param list<MenuNode> $items */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public array $items,
    ) {
    }
}
