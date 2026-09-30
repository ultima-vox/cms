<?php

declare(strict_types=1);

namespace Core\View;

use Core\View\Render\RenderContext;
use Core\View\Render\RenderResult;
use RuntimeException;

final readonly class FrontendRenderer
{
    public function __construct(
        private TwigRenderer $twig,
        private PhpRenderer $php,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function render(string $template, array $context = []): string
    {
        return $this->renderResult($template, $context)->html;
    }

    /** @param array<string, mixed> $context */
    public function renderResult(
        string $template,
        array $context = [],
        ?RenderContext $renderContext = null,
    ): RenderResult {
        if (str_ends_with($template, '.html.php')) {
            return $this->php->renderResult($template, $context, $renderContext);
        }

        if (str_ends_with($template, '.twig')) {
            return new RenderResult(
                $this->twig->render($template, $context),
                $renderContext ?? new RenderContext(),
            );
        }

        throw new RuntimeException(sprintf('Unsupported frontend template format: %s.', $template));
    }
}
