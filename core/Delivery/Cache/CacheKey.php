<?php

declare(strict_types=1);

namespace Core\Delivery\Cache;

use RuntimeException;

final class CacheKey
{
    private function __construct()
    {
    }

    public static function page(int $siteId, string $path, string $variant = 'public'): string
    {
        if ($siteId < 1) {
            throw new RuntimeException('Site id must be positive.');
        }

        $path = self::path($path);
        $variant = self::token($variant, 'cache variant');

        return sprintf('page:site:%d:variant:%s:path:%s', $siteId, $variant, $path);
    }

    public static function fragment(int $siteId, string $namespace, string $identity): string
    {
        if ($siteId < 1) {
            throw new RuntimeException('Site id must be positive.');
        }

        $identity = trim($identity);
        if ($identity === '' || strlen($identity) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $identity) === 1) {
            throw new RuntimeException('Invalid cache identity.');
        }

        return sprintf(
            'fragment:site:%d:%s:%s',
            $siteId,
            self::token($namespace, 'cache namespace'),
            hash('sha256', $identity),
        );
    }

    private static function path(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/' || strlen($path) > 2048 || str_contains($path, "\0")) {
            throw new RuntimeException('Invalid page cache path.');
        }

        return $path;
    }

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}$/D', $value) !== 1) {
            throw new RuntimeException(sprintf('Invalid %s.', $label));
        }

        return $value;
    }
}
