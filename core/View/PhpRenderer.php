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

final class PhpRenderer
{
    private ViewTemplateRenderer $viewRenderer;

    public function __construct(
        private readonly string $rootPath,
        private readonly ExtensionCore $core,
    ) {
        require_once __DIR__ . '/helpers.php';
        $this->viewRenderer = new ViewTemplateRenderer($rootPath, $core->templates());
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
        $file = $this->resolveTemplate($template);
        $renderContext ??= new RenderContext();
        $renderEngine = new RenderEngine($renderContext);
        $facadeContext = new TemplateFacadeContext(
            $renderEngine,
            $context,
            $this->viewRenderer,
        );
        $facades = $this->core->templates()->instantiateFacades($facadeContext);

        $variables = array_merge(
            ['core' => $this->core],
            $facades,
            $context,
        );

        $renderer = static function (string $__file, array $__variables): string {
            extract($__variables, EXTR_SKIP);
            ob_start();

            try {
                include $__file;

                return (string) ob_get_clean();
            } catch (\Throwable $exception) {
                ob_end_clean();
                throw $exception;
            }
        };

        return new RenderResult($renderer($file, $variables), $renderContext);
    }

    private function resolveTemplate(string $template): string
    {
        $template = trim($template);

        if (!preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_./-]*\.html\.php$#', $template)
            || str_contains($template, '..')) {
            throw new RuntimeException('Invalid PHP template path.');
        }

        $runtime = $this->rootPath . '/storage/templates/' . $template;
        if (is_file($runtime)) {
            return $runtime;
        }

        $packaged = $this->rootPath . '/templates/' . $template;
        if (is_file($packaged)) {
            return $packaged;
        }

        throw new RuntimeException(sprintf('PHP template "%s" was not found.', $template));
    }
}
