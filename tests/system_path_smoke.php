<?php

declare(strict_types=1);

use Core\Database;
use Core\Routing\SystemPathPolicy;

require dirname(__DIR__) . '/vendor/autoload.php';

foreach (['/admin', '/admin/foo', '/api/v1', '/health/unknown', '/assets/app.css', '/media/x', '/storage/x'] as $path) {
    if (!SystemPathPolicy::isReserved($path)) {
        throw new RuntimeException(sprintf('Reserved path was accepted: %s', $path));
    }
}

foreach (['/', '/about', '/administrator', '/apiary'] as $path) {
    if (SystemPathPolicy::isReserved($path)) {
        throw new RuntimeException(sprintf('Public path was reserved unexpectedly: %s', $path));
    }
}

$db = Database::connection();
try {
    $db->exec("INSERT INTO nodes (name, slug, path, status) VALUES ('Reserved', 'admin', '/admin', 'draft')");
    throw new RuntimeException('Database accepted a reserved node path.');
} catch (PDOException) {
    // Expected database CHECK constraint violation.
}

fwrite(STDOUT, "SYSTEM PATH OK\n");
