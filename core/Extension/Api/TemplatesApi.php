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

    /** @var list<Closure> */
    private array $facadeProviders = [];

    /** @var array<string, ViewDefinition> */
    private array $views = [];

    private bool $frozen = false;

    public function facade(string $variable, callable $factory): void
    {
        $this->assertMutable();
        $variable = trim($variable);
        $this->assertVariableName($variable);

        if ($this->isReservedVariable($variable)) {
            throw new RuntimeException(sprintf('Template variable $%s is reserved by the core.', $variable));
        }

        if (isset($this->facades[$variable])) {
            throw new RuntimeException(sprintf('Template facade $%s is already registered.', $variable));
        }

        $this->facades[$variable] = Closure::fromCallable($factory);
    }

    /**
     * Registers a provider that may expose context-dependent facade aliases.
     * The provider receives the current template context and already resolved fixed facades.
     *
     * @param callable(TemplateFacadeContext, array<string, object>): array<string, object> $provider
     */
    public function facadeProvider(callable $provider): void
    {
        $this->assertMutable();
        $this->facadeProviders[] = Closure::fromCallable($provider);
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

        if (!preg_match('/^[a-z][a-z0-9._-]{0,127}$/', $sourceType)) {
            throw new RuntimeException('View source type is invalid.');
        }

        if (!preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_./-]*\.php$#', $templatePath)
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
            $this->assertVariableAvailable($variable, $context, $result);
            $facade = $factory($context);
            if (!is_object($facade)) {
                throw new RuntimeException(sprintf('Template facade $%s factory must return an object.', $variable));
            }

            $result[$variable] = $facade;
        }

        foreach ($this->facadeProviders as $provider) {
            $provided = $provider($context, $result);
            if (!is_array($provided)) {
                throw new RuntimeException('Template facade provider must return an array.');
            }

            foreach ($provided as $variable => $facade) {
                if (!is_string($variable)) {
                    throw new RuntimeException('Dynamic template facade variable name must be a string.');
                }
                $this->assertVariableName($variable);
                $this->assertVariableAvailable($variable, $context, $result);
                if (!is_object($facade)) {
                    throw new RuntimeException(sprintf('Dynamic template facade $%s must be an object.', $variable));
                }

                $result[$variable] = $facade;
            }
        }

        return $result;
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertVariableName(string $variable): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,79}$/', $variable)) {
            throw new RuntimeException('Template facade variable name is invalid.');
        }
    }

    /** @param array<string, object> $resolved */
    private function assertVariableAvailable(
        string $variable,
        TemplateFacadeContext $context,
        array $resolved,
    ): void {
        if ($this->isReservedVariable($variable)) {
            throw new RuntimeException(sprintf('Template variable $%s is reserved by the core.', $variable));
        }
        if (array_key_exists($variable, $context->variables())) {
            throw new RuntimeException(sprintf('Template facade $%s conflicts with page context.', $variable));
        }
        if (isset($resolved[$variable])) {
            throw new RuntimeException(sprintf('Template facade $%s is already resolved.', $variable));
        }
    }

    private function isReservedVariable(string $variable): bool
    {
        return in_array($variable, ['core', 'page', 'site', 'node', 'content', 'items'], true);
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Template registry is frozen. Registration is only allowed during application boot.');
        }
    }
}
