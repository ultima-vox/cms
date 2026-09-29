<?php

declare(strict_types=1);

namespace Core\View\Render;

use Core\Extension\Api\TemplatesApi;
use RuntimeException;
use Throwable;

final readonly class ViewTemplateRenderer
{
    private string $rootRealPath;

    public function __construct(
        private string $rootPath,
        private TemplatesApi $templates,
    ) {
        $rootRealPath = realpath($rootPath);
        if ($rootRealPath === false) {
            throw new RuntimeException('Application root path does not exist.');
        }

        $this->rootRealPath = rtrim($rootRealPath, DIRECTORY_SEPARATOR);
        require_once dirname(__DIR__) . '/helpers.php';
    }

    /** @param array<string, mixed> $variables */
    public function render(string $viewCode, string $sourceType, array $variables = []): string
    {
        $definition = $this->templates->getView($viewCode);
        if ($definition->sourceType !== $sourceType) {
            throw new RuntimeException(sprintf(
                'View "%s" expects source type "%s", "%s" given.',
                $viewCode,
                $definition->sourceType,
                $sourceType,
            ));
        }

        $candidate = $this->rootPath . '/' . $definition->templatePath;
        $file = realpath($candidate);
        if ($file === false || !is_file($file)) {
            throw new RuntimeException(sprintf('View template "%s" was not found.', $definition->templatePath));
        }

        $prefix = $this->rootRealPath . DIRECTORY_SEPARATOR;
        if (!str_starts_with($file, $prefix)) {
            throw new RuntimeException('View template resolves outside the application root.');
        }

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
}
