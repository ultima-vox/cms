<?php

declare(strict_types=1);

namespace Core\View;

use RuntimeException;
use Throwable;

final class AdminPhpRenderer
{
    /** @var list<string> */
    private array $paths = [];

    /** @var array<string, mixed> */
    private array $globals = [];

    /** @param list<string> $additionalPaths */
    public function __construct(string $rootPath, array $additionalPaths = [])
    {
        require_once __DIR__ . '/helpers.php';

        $runtimeTemplates = $rootPath . '/storage/templates';
        $packagedTemplates = $rootPath . '/templates';

        if (is_dir($runtimeTemplates)) {
            $this->paths[] = $runtimeTemplates;
        }

        foreach ($additionalPaths as $path) {
            $this->addPath($path);
        }

        if (is_dir($packagedTemplates) && !in_array($packagedTemplates, $this->paths, true)) {
            $this->paths[] = $packagedTemplates;
        }
    }

    public function addGlobal(string $name, mixed $value): void
    {
        $name = trim($name);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,79}$/', $name) || $name === 'view') {
            throw new RuntimeException('Admin view global name is invalid or reserved.');
        }

        $this->globals[$name] = $value;
    }

    public function addPath(string $path, ?string $namespace = null, bool $prepend = false): void
    {
        if ($namespace !== null && $namespace !== '') {
            throw new RuntimeException('Admin PHP views do not support template namespaces.');
        }

        $path = rtrim($path, '/\\');
        if ($path === '' || !is_dir($path)) {
            throw new RuntimeException(sprintf('Admin template path does not exist: %s.', $path));
        }

        $this->paths = array_values(array_filter(
            $this->paths,
            static fn (string $existing): bool => $existing !== $path,
        ));

        if ($prepend) {
            array_unshift($this->paths, $path);
        } else {
            $this->paths[] = $path;
        }
    }

    /** @param array<string, mixed> $context */
    public function render(string $template, array $context = []): string
    {
        $file = $this->resolveTemplate($template);
        $variables = array_merge($context, $this->globals, ['view' => $this]);

        $renderer = static function (string $__file, array $__variables): string {
            extract($__variables, EXTR_SKIP);
            ob_start();

            try {
                include $__file;

                return (string) ob_get_clean();
            } catch (Throwable $exception) {
                ob_end_clean();
                throw $exception;
            }
        };

        return $renderer($file, $variables);
    }

    private function resolveTemplate(string $template): string
    {
        $template = trim($template);

        if (!preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_./-]*\.php$#', $template)
            || str_contains($template, '..')) {
            throw new RuntimeException('Invalid admin PHP template path.');
        }

        foreach ($this->paths as $path) {
            $file = $path . '/' . $template;
            if (is_file($file)) {
                return $file;
            }
        }

        throw new RuntimeException(sprintf('Admin PHP template "%s" was not found.', $template));
    }
}
