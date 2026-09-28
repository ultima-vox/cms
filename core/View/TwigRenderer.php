<?php

declare(strict_types=1);

namespace Core\View;

use RuntimeException;
use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Loader\FilesystemLoader;

final class TwigRenderer
{
    private Environment $twig;

    public function __construct(?string $templatesPath = null)
    {
        $path = $templatesPath ?? dirname(__DIR__, 2) . '/templates';

        if (!is_dir($path)) {
            throw new RuntimeException(sprintf('Каталог шаблонов %s не найден.', $path));
        }

        $loader = new FilesystemLoader($path);
        $this->twig = new Environment($loader, [
            'autoescape' => 'html',
            'cache' => false,
            'strict_variables' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string
    {
        try {
            return $this->twig->render($template, $context);
        } catch (TwigError $exception) {
            throw new RuntimeException(
                sprintf('Ошибка рендеринга шаблона %s: %s', $template, $exception->getMessage()),
                previous: $exception,
            );
        }
    }
}
