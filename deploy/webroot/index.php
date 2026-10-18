<?php

declare(strict_types=1);

// The web-server rules also protect private files before PHP is invoked.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (preg_match('~(?:^/app(?:/|$)|(?:^|/)\.)~i', $path) || str_contains($path, "\\")) {
    http_response_code(404);
    exit;
}

return require __DIR__ . '/app/public/index.php';
