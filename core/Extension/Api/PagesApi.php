<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Core\Page\PageExecutorInterface;
use Core\Page\PageTypeDefinition;
use LogicException;
use RuntimeException;

final class PagesApi
{
    /** @var array<string, PageTypeDefinition> */
    private array $definitions = [];

    private ?string $defaultCode = null;
    private bool $frozen = false;

    public function type(PageTypeDefinition $definition): void
    {
        $this->assertMutable();

        if (isset($this->definitions[$definition->code])) {
            throw new RuntimeException(sprintf('Page type "%s" is already registered.', $definition->code));
        }

        if ($definition->isDefault) {
            if ($this->defaultCode !== null) {
                throw new RuntimeException(sprintf(
                    'Default page type is already registered: %s.',
                    $this->defaultCode,
                ));
            }
            $this->defaultCode = $definition->code;
        }

        $this->definitions[$definition->code] = $definition;
    }

    /**
     * Transitional shorthand for executor-only registrations.
     * New extensions should register a PageTypeDefinition via type().
     */
    public function executor(string $code, PageExecutorInterface $executor): void
    {
        $normalized = strtolower(trim($code));
        $this->type(new PageTypeDefinition(
            code: $normalized,
            name: $normalized,
            executor: $executor,
        ));
    }

    public function has(string $code): bool
    {
        return isset($this->definitions[strtolower(trim($code))]);
    }

    public function definition(string $code): PageTypeDefinition
    {
        $code = strtolower(trim($code));

        return $this->definitions[$code]
            ?? throw new RuntimeException(sprintf('Unknown page type: %s.', $code));
    }

    public function resolve(string $code): PageExecutorInterface
    {
        return $this->definition($code)->executor;
    }

    /** @return list<PageTypeDefinition> */
    public function definitions(): array
    {
        $definitions = array_values($this->definitions);
        usort(
            $definitions,
            static fn (PageTypeDefinition $left, PageTypeDefinition $right): int =>
                [$left->sorting, $left->name, $left->code] <=> [$right->sorting, $right->name, $right->code],
        );

        return $definitions;
    }

    public function default(): ?PageTypeDefinition
    {
        return $this->defaultCode !== null
            ? $this->definitions[$this->defaultCode]
            : null;
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function validateConfiguration(string $code, array $configuration): array
    {
        return $this->definition($code)->validateConfiguration($configuration);
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Page type registry is frozen. Registration is only allowed during application boot.');
        }
    }
}
