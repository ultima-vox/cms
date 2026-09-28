<?php

declare(strict_types=1);

namespace Core\Security;

use RuntimeException;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    private function __construct()
    {
    }

    public static function token(): string
    {
        self::ensureSession();

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        self::ensureSession();

        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($expected)
            && is_string($token)
            && hash_equals($expected, $token);
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Сессия должна быть запущена до использования CSRF-защиты.');
        }
    }
}
