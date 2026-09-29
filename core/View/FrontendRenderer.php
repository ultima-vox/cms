<?php

declare(strict_types=1);

namespace Core\View;

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
        if (str_ends_with($template, '.html.php')) {
            return $this->php->render($template, $context);
        }

        if (str_ends_with($template, '.twig')) {
            return $this->twig->render($template, $context);
        }

        throw new RuntimeException(sprintf('Unsupported frontend template format: %s.', $template));
    }
}
