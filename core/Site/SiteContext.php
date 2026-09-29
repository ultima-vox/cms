<?php

declare(strict_types=1);

namespace Core\Site;

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

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
