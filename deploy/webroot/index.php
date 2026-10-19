<?php

declare(strict_types=1);

// The web-server rules also protect private files before PHP is invoked.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (preg_match('~(?:^/app(?:/|$)|(?:^|/)\.)~i', $path) || str_contains($path, "\\")) {
    http_response_code(404);
    exit;
}

// The development router must use the actual webroot assets, including installation probes.
if (PHP_SAPI === 'cli-server' && str_starts_with($path, '/assets/')) {
    $asset = realpath(__DIR__ . $path);
    if ($asset !== false && str_starts_with($asset, __DIR__ . '/assets/') && is_file($asset)) {
        return false;
    }
}

return require __DIR__ . '/app/public/index.php';
