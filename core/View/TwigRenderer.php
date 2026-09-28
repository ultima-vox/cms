<?php

declare(strict_types=1);

namespace Core\View;

use Core\Config;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigRenderer
{
    private Environment $twig;

    public function __construct(string $rootPath)
    {
        $loader = new FilesystemLoader($rootPath . '/templates');

        $cacheDirectory = $rootPath . '/storage/cache/twig';
        $cache = Config::environment() === 'production' ? $cacheDirectory : false;

        $this->twig = new Environment($loader, [
            'cache' => $cache,
            'auto_reload' => Config::environment() !== 'production',
            'autoescape' => 'html',
            'strict_variables' => Config::debug(),
        ]);
    }

    /** @param array<string, mixed> $context */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }
}
