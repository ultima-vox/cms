<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Core\Delivery\Cache\CacheBackendInterface;
use Core\Delivery\Cache\CacheKey;
use RuntimeException;

final readonly class CacheApi
{
    public function __construct(private CacheBackendInterface $backend)
    {
    }

    public function get(string $key): ?string
    {
        return $this->backend->get($key);
    }

    /** @param list<string> $tags */
    public function put(string $key, string $value, array $tags = [], ?int $ttlSeconds = null): void
    {
        $this->backend->put($key, $value, $tags, $ttlSeconds);
    }

    /**
     * @param callable():string $resolver
     * @param list<string> $tags
     */
    public function remember(
        string $key,
        callable $resolver,
        array $tags = [],
        ?int $ttlSeconds = null,
    ): string {
        $cached = $this->backend->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $resolver();
        if (!is_string($value)) {
            throw new RuntimeException('Cache resolver must return a string.');
        }
        $this->backend->put($key, $value, $tags, $ttlSeconds);

        return $value;
    }

    public function forget(string $key): void
    {
        $this->backend->delete($key);
    }

    /** @param list<string> $tags */
    public function invalidate(array $tags): int
    {
        return $this->backend->invalidate($tags);
    }

    public function pageKey(int $siteId, string $path, string $variant = 'public'): string
    {
        return CacheKey::page($siteId, $path, $variant);
    }

    public function fragmentKey(int $siteId, string $namespace, string $identity): string
    {
        return CacheKey::fragment($siteId, $namespace, $identity);
    }
}
