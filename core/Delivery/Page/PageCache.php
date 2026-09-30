<?php

declare(strict_types=1);

namespace Core\Delivery\Page;

use Core\Extension\Api\CacheApi;
use Core\View\Render\RenderResult;

final readonly class PageCache
{
    public function __construct(
        private CacheApi $cache,
        private int $defaultMaxAge = 300,
    ) {
    }

    public function get(int $siteId, string $path, string $variant = 'public'): ?PageCacheEntry
    {
        $key = $this->cache->pageKey($siteId, $path, $variant);
        $payload = $this->cache->get($key);
        if ($payload === null) {
            return null;
        }

        $entry = PageCacheEntry::decode($payload);
        if ($entry === null) {
            $this->cache->forget($key);
            return null;
        }

        return $entry;
    }

    public function put(
        int $siteId,
        string $path,
        string $variant,
        RenderResult $result,
    ): ?PageCacheEntry {
        if (!$result->context->isCacheable()) {
            return null;
        }

        $maxAge = $result->context->maxAge($this->defaultMaxAge);
        if ($maxAge === null || $maxAge < 1) {
            return null;
        }

        $dependencies = $result->context->dependencies();
        if (!in_array('site:' . $siteId, $dependencies, true)) {
            $dependencies[] = 'site:' . $siteId;
        }

        $entry = new PageCacheEntry(
            $result->html,
            $dependencies,
            $result->context->resourceHints(),
            time(),
            $maxAge,
        );

        $this->cache->put(
            $this->cache->pageKey($siteId, $path, $variant),
            $entry->encode(),
            $dependencies,
            $maxAge,
        );

        return $entry;
    }
}
