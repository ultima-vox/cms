<?php

declare(strict_types=1);

namespace Core\Delivery\Cache;

interface CacheBackendInterface
{
    public function get(string $key): ?string;

    /** @param list<string> $tags */
    public function put(string $key, string $value, array $tags = [], ?int $ttlSeconds = null): void;

    public function delete(string $key): void;

    /** @param list<string> $tags */
    public function invalidate(array $tags): int;
}
