<?php

declare(strict_types=1);

namespace Core;

use RuntimeException;

final class Config
{
    private function __construct()
    {
    }

    public static function string(string $key, ?string $default = null): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            if ($default !== null) {
                return $default;
            }

            throw new RuntimeException(sprintf('Не задана переменная окружения %s.', $key));
        }

        return (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($parsed === null) {
            throw new RuntimeException(sprintf('Переменная %s должна быть boolean.', $key));
        }

        return $parsed;
    }

    public static function environment(): string
    {
        return self::string('APP_ENV', 'production');
    }

    public static function debug(): bool
    {
        return self::bool('APP_DEBUG', false);
    }
}
