<?php

declare(strict_types=1);

namespace Core\Page;

use Core\View\SafeHtml;
use RuntimeException;

final readonly class PageRuntime
{
    public function __construct(
        private PageExecutionContext $context,
        private PageExecutionChain $chain,
    ) {
    }

    public function start(): SafeHtml
    {
        return $this->chain->start($this);
    }

    public function execute(): SafeHtml
    {
        return $this->chain->continue($this);
    }

    public function context(): PageExecutionContext
    {
        return $this->context;
    }

    public function id(): int
    {
        $id = $this->context->node['id'] ?? null;
        if (!is_numeric($id) || (int) $id < 1) {
            throw new RuntimeException('Page runtime requires a positive node id.');
        }

        return (int) $id;
    }

    public function name(): string
    {
        return (string) ($this->context->node['name'] ?? '');
    }

    public function title(): string
    {
        $title = trim((string) ($this->context->node['title'] ?? ''));

        return $title !== '' ? $title : $this->name();
    }

    public function path(): string
    {
        $path = (string) ($this->context->node['path'] ?? '');

        return $path !== '' ? $path : $this->context->request->path;
    }

    public function metaDescription(): ?string
    {
        $description = $this->context->node['meta_description'] ?? null;
        if (!is_string($description)) {
            return null;
        }

        $description = trim($description);

        return $description !== '' ? $description : null;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->context->configuration[$key] ?? $default;
    }
}
