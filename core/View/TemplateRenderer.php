<?php

declare(strict_types=1);

namespace Core\View;

use Core\Extension\Core as ExtensionCore;
use Core\View\Render\RenderContext;
use Core\View\Render\RenderEngine;
use Core\View\Render\RenderResult;
use Core\View\Render\TemplateFacadeContext;
use Core\View\Render\ViewTemplateRenderer;
use RuntimeException;
use Throwable;

class TemplateRenderer
{
    /** @var list<string> */
    private array $roots = [];

    /** @var array<string, mixed> */
    private array $globals = [];

    private ViewTemplateRenderer $viewRenderer;

    /** @param list<string> $additionalPaths */
    public function __construct(
        private readonly string $rootPath,
        private readonly ExtensionCore $core,
        array $additionalPaths = [],
    ) {
        require_once __DIR__ . '/helpers.php';

        $runtimeTemplates = $rootPath . '/storage/templates';
        if (is_dir($runtimeTemplates)) {
            $this->roots[] = $this->canonicalRoot($runtimeTemplates);
        }

        foreach ($additionalPaths as $path) {
            if (is_dir($path)) {
                $root = $this->canonicalRoot($path);
                if (!in_array($root, $this->roots, true)) {
                    $this->roots[] = $root;
                }
            }
        }

        $this->roots[] = $this->canonicalRoot($rootPath . '/templates');
        $this->viewRenderer = new ViewTemplateRenderer($rootPath, $core->templates());
    }

    public function addGlobal(string $name, mixed $value): void
    {
        $name = trim($name);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,79}$/', $name)) {
            throw new RuntimeException('Template global name is invalid.');
        }

        $this->globals[$name] = $value;
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
        $file = $this->resolveTemplate($this->normalizeLegacyPath($template));
        $renderContext ??= new RenderContext();
        $renderEngine = new RenderEngine($renderContext);
        $facadeContext = new TemplateFacadeContext($renderEngine, $context, $this->viewRenderer);
        $facades = $this->core->templates()->instantiateFacades($facadeContext);

        $variables = array_merge($this->globals, ['core' => $this->core], $facades, $context);
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

        return new RenderResult($renderer($file, $variables), $renderContext);
    }

    private function normalizeLegacyPath(string $template): string
    {
        if (str_ends_with($template, '.html.php')) {
            return substr($template, 0, -strlen('.html.php')) . '.php';
        }
        if (str_ends_with($template, '.twig')) {
            return substr($template, 0, -strlen('.twig')) . '.php';
        }

        return $template;
    }

    private function resolveTemplate(string $template): string
    {
        $template = trim($template);
        if (!preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_./-]*\.php$#', $template)
            || str_contains($template, '..')
            || str_contains($template, '://')) {
            throw new RuntimeException('Invalid PHP template path.');
        }

        foreach ($this->roots as $root) {
            $candidate = realpath($root . DIRECTORY_SEPARATOR . $template);
            if ($candidate === false || !is_file($candidate)) {
                continue;
            }
            if (!str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Template resolves outside its allowed root.');
            }
            return $candidate;
        }

        throw new RuntimeException(sprintf('PHP template "%s" was not found.', $template));
    }

    private function canonicalRoot(string $path): string
    {
        $root = realpath($path);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException(sprintf('Template root does not exist: %s.', $path));
        }
        return rtrim($root, DIRECTORY_SEPARATOR);
    }
}
