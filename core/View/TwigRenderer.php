<?php

declare(strict_types=1);

namespace Core\View;

use Core\Config;
use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigRenderer
{
    private Environment $twig;
    private FilesystemLoader $loader;

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

        $this->loader = new FilesystemLoader($paths);

        $cacheDirectory = $rootPath . '/storage/cache/twig';
        $cache = Config::environment() === 'production' ? $cacheDirectory : false;

        $this->twig = new Environment($this->loader, [
            'cache' => $cache,
            'auto_reload' => Config::environment() !== 'production',
            'autoescape' => 'html',
            'strict_variables' => Config::debug(),
        ]);
    }

    public function addGlobal(string $name, mixed $value): void
    {
        $name = trim($name);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,79}$/', $name)) {
            throw new RuntimeException('Twig global name is invalid.');
        }

        $this->twig->addGlobal($name, $value);
    }

    public function addPath(string $path, ?string $namespace = null, bool $prepend = false): void
    {
        $path = rtrim($path, '/\\');
        if ($path === '' || !is_dir($path)) {
            throw new RuntimeException(sprintf('Twig template path does not exist: %s.', $path));
        }

        if ($namespace === null || $namespace === '') {
            if ($prepend) {
                $this->loader->prependPath($path);
            } else {
                $this->loader->addPath($path);
            }

            return;
        }

        if (!preg_match('/^[a-z][a-z0-9._-]{0,79}$/', $namespace)) {
            throw new RuntimeException('Twig namespace is invalid.');
        }

        if ($prepend) {
            $this->loader->prependPath($path, $namespace);
        } else {
            $this->loader->addPath($path, $namespace);
        }
    }

    /** @param array<string, mixed> $context */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }
}
