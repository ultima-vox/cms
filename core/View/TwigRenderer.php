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

    private bool $rendered = false;

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

    /** @param array<string, mixed> $globals */
    public function addGlobals(array $globals): void
    {
        if ($this->rendered) {
            throw new RuntimeException('Twig globals must be registered before the first render.');
        }

        foreach ($globals as $name => $value) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,79}$/', $name)) {
                throw new RuntimeException(sprintf('Invalid Twig global name: %s.', $name));
            }

            $this->twig->addGlobal($name, $value);
        }
    }

    /** @param array<string, mixed> $context */
    public function render(string $template, array $context = []): string
    {
        $this->rendered = true;

        return $this->twig->render($template, $context);
    }
}
