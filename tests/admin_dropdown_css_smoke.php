<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root . '/public/assets/admin/dropdown.css';

if (!is_file($path) || !is_readable($path)) {
    throw new RuntimeException('Missing admin dropdown stylesheet.');
}

$css = file_get_contents($path);
if ($css === false) {
    throw new RuntimeException('Unable to read admin dropdown stylesheet.');
}

foreach ([
    '.admin-dropdown',
    '.admin-dropdown__menu',
    '.admin-dropdown__item',
    '.admin-dropdown__item--danger',
    '.admin-dropdown__divider',
    '.admin-dropdown--end',
    'var(--uv-shadow-popover)',
    'var(--uv-danger-soft)',
    ':focus-visible',
    '[aria-disabled="true"]',
    'calc(100vw - 24px)',
] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException('Missing dropdown CSS contract: ' . $contract);
    }
}

echo "Admin dropdown CSS smoke: OK\n";
