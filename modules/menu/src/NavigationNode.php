<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu;

use RuntimeException;

final readonly class NavigationNode
{
    /** @param list<NavigationNode> $children */
    public function __construct(
        public string $key,
        public string $label,
        public string $url,
        public array $children = [],
    ) {
        if (trim($key) === '') {
            throw new RuntimeException('Navigation node key cannot be empty.');
        }
        if (trim($label) === '') {
            throw new RuntimeException('Navigation node label cannot be empty.');
        }
        if (trim($url) === '') {
            throw new RuntimeException('Navigation node URL cannot be empty.');
        }
        foreach ($children as $child) {
            if (!$child instanceof self) {
                throw new RuntimeException('Navigation node children must be NavigationNode instances.');
            }
        }
    }
}
