<?php

declare(strict_types=1);

namespace Core\Delivery\Cache;

interface CacheStoreInterface
{
    public function get(string $key): ?string;

    public function put(string $key, string $value, ?int $ttlSeconds = null): void;

    public function delete(string $key): void;
}
