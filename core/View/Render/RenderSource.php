<?php

declare(strict_types=1);

namespace Core\View\Render;

abstract class RenderSource implements RenderNodeInterface
{
    public function __construct(protected readonly RenderEngine $renderEngine)
    {
    }

    final public function show(): string
    {
        return $this->renderEngine->render($this);
    }

    final public function add(RenderNodeInterface $child): Composition
    {
        return new Composition($this->renderEngine, $this, [$child]);
    }
}
