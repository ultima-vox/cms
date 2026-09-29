<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Closure;
use Core\View\Render\TemplateFacadeContext;
use LogicException;
use RuntimeException;

final class TemplatesApi
{
    /** @var array<string, Closure> */
    private array $facades = [];

    /** @var array<string, ViewDefinition> */
    private array $views = [];

    private bool $frozen = false;

    public function facade(string $variable, callable $factory): void
    {
        $this->assertMutable();
        $variable = trim($variable);

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,79}$/', $variable)) {
            throw new RuntimeException('Template facade variable name is invalid.');
        }

        if (in_array($variable, ['core', 'page', 'site'], true)) {
            throw new RuntimeException(sprintf('Template variable $%s is reserved by the core.', $variable));
        }

        if (isset($this->facades[$variable])) {
            throw new RuntimeException(sprintf('Template facade $%s is already registered.', $variable));
        }

        $this->facades[$variable] = Closure::fromCallable($factory);
    }

    public function view(string $code, string $sourceType, string $templatePath): void
    {
        $this->assertMutable();
        $code = trim($code);
        $sourceType = trim($sourceType);
        $templatePath = trim($templatePath);

        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $code)) {
            throw new RuntimeException('View code is invalid.');
        }

        if ($sourceType === '') {
            throw new RuntimeException('View source type is required.');
        }

        if (!preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_./-]*\.html\.php$#', $templatePath)
            || str_contains($templatePath, '..')) {
            throw new RuntimeException('View template path is invalid.');
        }

        if (isset($this->views[$code])) {
            throw new RuntimeException(sprintf('View "%s" is already registered.', $code));
        }

        $this->views[$code] = new ViewDefinition($code, $sourceType, $templatePath);
    }

    public function getView(string $code): ViewDefinition
    {
        return $this->views[$code]
            ?? throw new RuntimeException(sprintf('Unknown view template: %s.', $code));
    }

    /** @return array<string, object> */
    public function instantiateFacades(TemplateFacadeContext $context): array
    {
        $result = [];

        foreach ($this->facades as $variable => $factory) {
            $facade = $factory($context);
            if (!is_object($facade)) {
                throw new RuntimeException(sprintf('Template facade $%s factory must return an object.', $variable));
            }
            $result[$variable] = $facade;
        }

        return $result;
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Template registry is frozen. Registration is only allowed during application boot.');
        }
    }
}
