<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'UltimaVox\\Modules\\Infosystem\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    if ($relative === false || $relative === '') {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
