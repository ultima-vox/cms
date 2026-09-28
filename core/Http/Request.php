<?php

declare(strict_types=1);

namespace Core\Http;

final readonly class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     */
    public function __construct(
        public string $method,
        public string $uri,
        public string $path,
        public array $query,
        public array $post,
        public array $server,
        public array $cookies,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return new self(
            method: $method,
            uri: $uri,
            path: is_string($path) && $path !== '' ? $path : '/',
            query: $_GET,
            post: $_POST,
            server: $_SERVER,
            cookies: $_COOKIE,
        );
    }
}
