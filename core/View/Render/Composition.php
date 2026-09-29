<?php

declare(strict_types=1);

namespace Core\View\Render;

final class Composition implements RenderNodeInterface
{
    /** @param list<RenderNodeInterface> $children */
    public function __construct(
        private readonly RenderEngine $renderEngine,
        private readonly RenderNodeInterface $root,
        private array $children = [],
    ) {
    }

    public function add(RenderNodeInterface $child): self
    {
        $clone = clone $this;
        $clone->children[] = $child;

        return $clone;
    }

    public function show(): string
    {
        return $this->renderEngine->render($this);
    }

    public function render(RenderContext $context): string
    {
        $html = $this->root->render($context);

        foreach ($this->children as $child) {
            $html .= $child->render($context);
        }

        return $html;
    }
}
