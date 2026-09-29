<?php

declare(strict_types=1);

namespace Core\View\Render;

interface RenderNodeInterface
{
    public function render(RenderContext $context): string;
}
