<?php

declare(strict_types=1);

namespace Core\Site;

use Core\Routing\Permalink;
use RuntimeException;

final readonly class SiteContext
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $host,
        public array $settings = [],
    ) {
    }

    public function staticPermalinkStyle(): string
    {
        $style = $this->setting('permalink_style', 'clean');
        if (!is_string($style)) {
            throw new RuntimeException('Site permalink style must be a string.');
        }
        return (new Permalink('/', $style))->style;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
