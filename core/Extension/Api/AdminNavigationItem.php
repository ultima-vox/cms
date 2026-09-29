<?php

declare(strict_types=1);

namespace Core\Extension\Api;

final readonly class AdminNavigationItem
{
    public function __construct(
        public string $code,
        public string $label,
        public string $path,
        public ?string $permission,
        public int $order,
    ) {
    }
}
