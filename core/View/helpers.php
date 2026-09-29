<?php

declare(strict_types=1);

use Core\View\SafeHtml;

if (!function_exists('text')) {
    function text(string|int|float|null $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8',
            true,
        );
    }
}

if (!function_exists('html')) {
    function html(SafeHtml $value): string
    {
        return $value->value();
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $path = ltrim(trim($path), '/');

        if ($path === ''
            || str_contains($path, '..')
            || !preg_match('#^[A-Za-z0-9._~/%+-]+$#', $path)) {
            throw new InvalidArgumentException('Invalid asset path.');
        }

        return '/assets/' . $path;
    }
}

if (!function_exists('url')) {
    function url(string $path = '/', bool $absolute = false): string
    {
        $path = trim($path);

        if ($path === ''
            || $path[0] !== '/'
            || preg_match("/[\\x00-\\x20\"'<>\\\\]/u", $path)) {
            throw new InvalidArgumentException('Invalid internal URL.');
        }

        $value = $path;

        if ($absolute) {
            $base = rtrim((string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: ''), '/');
            if ($base === '' || filter_var($base, FILTER_VALIDATE_URL) === false) {
                throw new RuntimeException('A valid APP_URL is required for absolute URLs.');
            }

            $value = $base . $path;
        }

        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8',
            true,
        );
    }
}
