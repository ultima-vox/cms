<?php

declare(strict_types=1);

namespace Core\View\Render;

final readonly class TemplateFacadeContext
{
    /** @param array<string, mixed> $variables */
    public function __construct(
        private RenderEngine $renderEngine,
        private array $variables,
        private ViewTemplateRenderer $viewRenderer,
    ) {
    }

    public function renderEngine(): RenderEngine
    {
        return $this->renderEngine;
    }

    /** @return array<string, mixed> */
    public function variables(): array
    {
        return $this->variables;
    }

    /** @param array<string, mixed> $variables */
    public function renderView(string $viewCode, string $sourceType, array $variables = []): string
    {
        return $this->viewRenderer->render($viewCode, $sourceType, $variables);
    }
}
