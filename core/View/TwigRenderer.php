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
        $runtimeTemplates = $rootPath . '/storage/templates';
        $packagedTemplates = $rootPath . '/templates';

        if (!is_dir($runtimeTemplates)) {
            @mkdir($runtimeTemplates, 0775, true);
        }

        $paths = is_dir($runtimeTemplates)
            ? [$runtimeTemplates, $packagedTemplates]
            : [$packagedTemplates];

        $loader = new FilesystemLoader($paths);

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
