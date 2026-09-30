<?php

declare(strict_types=1);

namespace Core\View\Render;

final readonly class RenderResult
{
    public function __construct(
        public string $html,
        public RenderContext $context,
    ) {
    }
}
