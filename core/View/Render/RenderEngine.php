<?php

declare(strict_types=1);

namespace Core\View\Render;

final class RenderEngine
{
    public function __construct(private readonly RenderContext $context = new RenderContext())
    {
    }

    public function render(RenderNodeInterface $node): string
    {
        return $node->render($this->context);
    }

    public function context(): RenderContext
    {
        return $this->context;
    }
}
