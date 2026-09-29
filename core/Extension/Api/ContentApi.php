<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Closure;
use Core\View\Render\RenderNodeInterface;
use Core\View\Render\TemplateFacadeContext;
use LogicException;
use RuntimeException;

final class ContentApi
{
    /** @var array<string, Closure> */
    private array $factories = [];

    private bool $frozen = false;

    public function source(string $type, callable $factory): void
    {
        $this->assertMutable();
        $type = trim($type);

        if (!preg_match('/^[a-z][a-z0-9._-]{0,127}$/', $type)) {
            throw new RuntimeException('Content source type is invalid.');
        }
        if (isset($this->factories[$type])) {
            throw new RuntimeException(sprintf('Content source "%s" is already registered.', $type));
        }

        $this->factories[$type] = Closure::fromCallable($factory);
    }

    /** @param array<string, mixed> $options */
    public function make(string $type, TemplateFacadeContext $context, array $options = []): RenderNodeInterface
    {
        $factory = $this->factories[$type] ?? null;
        if (!$factory instanceof Closure) {
            throw new RuntimeException(sprintf('Unknown content source: %s.', $type));
        }

        $source = $factory($context, $options);
        if (!$source instanceof RenderNodeInterface) {
            throw new RuntimeException(sprintf('Content source "%s" factory must return RenderNodeInterface.', $type));
        }

        return $source;
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Content registry is frozen. Registration is only allowed during application boot.');
        }
    }
}
