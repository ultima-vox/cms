<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Core\Page\PageExecutorInterface;
use LogicException;
use RuntimeException;

final class PagesApi
{
    /** @var array<string, PageExecutorInterface> */
    private array $executors = [];

    private bool $frozen = false;

    public function executor(string $code, PageExecutorInterface $executor): void
    {
        $this->assertMutable();

        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z][a-z0-9._-]{0,127}$/', $code)) {
            throw new RuntimeException('Page executor code is invalid.');
        }

        if (isset($this->executors[$code])) {
            throw new RuntimeException(sprintf('Page executor "%s" is already registered.', $code));
        }

        $this->executors[$code] = $executor;
    }

    public function has(string $code): bool
    {
        return isset($this->executors[strtolower(trim($code))]);
    }

    public function resolve(string $code): PageExecutorInterface
    {
        $code = strtolower(trim($code));

        return $this->executors[$code]
            ?? throw new RuntimeException(sprintf('Unknown page executor: %s.', $code));
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Page executor registry is frozen. Registration is only allowed during application boot.');
        }
    }
}
