<?php

declare(strict_types=1);

namespace Core\Layout;

final readonly class LayoutDefinition
{
    public function __construct(
        public int $id,
        public ?int $parentId,
        public string $name,
        public string $templatePath,
    ) {
    }
}
