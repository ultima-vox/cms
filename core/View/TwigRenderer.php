<?php

declare(strict_types=1);

namespace Core\View;

use Core\Config;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigRenderer
{
    private Environment $twig;

    /** @param list<string> $additionalPaths */
    public function __construct(string $rootPath, array $additionalPaths = [])
    {
        $runtimeTemplates = $rootPath . '/storage/templates';
        $packagedTemplates = $rootPath . '/templates';

        if (!is_dir($runtimeTemplates)) {
            @mkdir($runtimeTemplates, 0775, true);
        }

        $paths = [];
        if (is_dir($runtimeTemplates)) {
            $paths[] = $runtimeTemplates;
        }

        foreach ($additionalPaths as $path) {
            $path = rtrim($path, '/\\');
            if ($path !== '' && is_dir($path) && !in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        if (!in_array($packagedTemplates, $paths, true)) {
            $paths[] = $packagedTemplates;
        }

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
