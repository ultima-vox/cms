<?php

declare(strict_types=1);

namespace Core\Delivery;

use RuntimeException;

final readonly class ResourceHint
{
    /** @param array<string, string> $attributes */
    public function __construct(
        public string $rel,
        public string $href,
        public array $attributes = [],
    ) {
        if (!in_array($this->rel, ['preload', 'preconnect', 'dns-prefetch', 'modulepreload'], true)) {
            throw new RuntimeException('Unsupported resource hint relation.');
        }
        if ($this->href === ''
            || strlen($this->href) > 4096
            || preg_match('/[\x00-\x1F\x7F<>"]/', $this->href) === 1
            || str_contains($this->href, '\\')) {
            throw new RuntimeException('Invalid resource hint URL.');
        }

        $allowed = ['as', 'type', 'crossorigin', 'fetchpriority', 'imagesrcset', 'imagesizes'];
        foreach ($this->attributes as $name => $value) {
            if (!in_array($name, $allowed, true)
                || strlen($value) > 8192
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
                throw new RuntimeException(sprintf('Invalid resource hint attribute: %s.', $name));
            }
        }
    }

    public function key(): string
    {
        return hash('sha256', json_encode(
            [$this->rel, $this->href, $this->attributes],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }
}
