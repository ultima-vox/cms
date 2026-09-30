<?php

declare(strict_types=1);

namespace Core\Delivery\Cache;

final readonly class TaggedCache implements CacheBackendInterface
{
    public function __construct(
        private CacheStoreInterface $store,
        private FilesystemTagIndex $index,
    ) {
    }

    public function get(string $key): ?string
    {
        return $this->store->get($key);
    }

    public function put(string $key, string $value, array $tags = [], ?int $ttlSeconds = null): void
    {
        $this->index->publish(
            $key,
            $tags,
            function () use ($key, $value, $ttlSeconds): void {
                try {
                    $this->store->put($key, $value, $ttlSeconds);
                } catch (\Throwable $exception) {
                    $this->store->delete($key);
                    throw $exception;
                }
            },
        );
    }

    public function delete(string $key): void
    {
        $this->index->remove(
            $key,
            function () use ($key): void {
                $this->store->delete($key);
            },
        );
    }

    public function invalidate(array $tags): int
    {
        return $this->index->invalidate(
            $tags,
            function (string $key): void {
                $this->store->delete($key);
            },
        );
    }
}
