<?php

declare(strict_types=1);

namespace Core\Delivery\Page;

use Core\Http\Request;

final class PageCachePolicy
{
    private function __construct()
    {
    }

    public static function requestEligible(Request $request, bool $authenticated): bool
    {
        if ($authenticated || $request->method !== 'GET' || $request->query !== []) {
            return false;
        }

        $authorization = $request->server['HTTP_AUTHORIZATION'] ?? null;
        if (is_string($authorization) && trim($authorization) !== '') {
            return false;
        }

        $cacheControl = strtolower((string) ($request->server['HTTP_CACHE_CONTROL'] ?? ''));
        if (str_contains($cacheControl, 'no-cache') || str_contains($cacheControl, 'no-store')) {
            return false;
        }

        return true;
    }
}
