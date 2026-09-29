<?php

declare(strict_types=1);

namespace Core\Routing;

final class SystemPathPolicy
{
    /** @var list<string> */
    private const PREFIXES = [
        '/admin',
        '/api',
        '/health',
        '/assets',
        '/media',
        '/storage',
    ];

    public static function isReserved(string $path): bool
    {
        $normalized = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?: '/', '/');
        $normalized = rtrim($normalized, '/') ?: '/';

        foreach (self::PREFIXES as $prefix) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function prefixes(): array
    {
        return self::PREFIXES;
    }

    private function __construct()
    {
    }
}
