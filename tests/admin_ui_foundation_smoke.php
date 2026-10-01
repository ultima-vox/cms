<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requiredFiles = [
    'public/assets/admin.css',
    'public/assets/admin.js',
    'public/assets/admin/js/app.js',
    'public/assets/admin/js/core/csrf.js',
    'public/assets/admin/js/core/dom.js',
    'public/assets/admin/js/core/http.js',
    'public/assets/admin/js/core/storage.js',
    'public/assets/admin/js/components/command-palette.js',
    'public/assets/admin/js/components/dialog.js',
    'public/assets/admin/js/components/dropdown.js',
    'public/assets/admin/js/components/sidebar.js',
    'public/assets/admin/js/components/site-switcher.js',
];

foreach ($requiredFiles as $relativePath) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Missing admin UI asset: ' . $relativePath);
    }
}

$read = static function (string $relativePath) use ($root): string {
    $content = file_get_contents($root . '/' . $relativePath);
    if ($content === false) {
        throw new RuntimeException('Unable to read admin UI asset: ' . $relativePath);
    }

    return $content;
};

$assertContains = static function (string $haystack, string $needle, string $message): void {
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
};

$css = $read('public/assets/admin.css');
foreach ([
    '--uv-accent:',
    '--uv-control-height:',
    '--uv-focus-ring:',
    '.admin-button',
    '.admin-input',
    '.admin-table',
    '.admin-dialog',
    '@media (prefers-reduced-motion: reduce)',
] as $contract) {
    $assertContains($css, $contract, 'Missing admin CSS contract: ' . $contract);
}

$bootstrap = $read('public/assets/admin.js');
$assertContains(
    $bootstrap,
    "import('/assets/admin/js/app.js')",
    'Legacy admin.js entrypoint must load the modular application.',
);

$app = $read('public/assets/admin/js/app.js');
foreach ([
    './components/command-palette.js',
    './components/dialog.js',
    './components/dropdown.js',
    './components/sidebar.js',
    './components/site-switcher.js',
] as $module) {
    $assertContains($app, $module, 'Admin application does not initialize module: ' . $module);
}

$csrf = $read('public/assets/admin/js/core/csrf.js');
$assertContains($csrf, 'meta[name="csrf-token"]', 'CSRF helper must support a server-rendered meta token.');
$assertContains($csrf, 'input[name="${DEFAULT_FIELD}"]', 'CSRF helper must support the existing hidden form field.');
if (str_contains($csrf, 'localStorage')) {
    throw new RuntimeException('CSRF tokens must not be read from localStorage.');
}

$http = $read('public/assets/admin/js/core/http.js');
$assertContains($http, "credentials = 'same-origin'", 'Admin Fetch must default to same-origin credentials.');
$assertContains($http, 'if (!response.ok)', 'Admin Fetch must reject non-success HTTP responses.');

$dialog = $read('public/assets/admin/js/components/dialog.js');
$assertContains($dialog, '[data-dialog-open]', 'Dialog component must use a stable behavior hook.');
$assertContains($dialog, '[data-dialog-close]', 'Dialog component must expose a stable close hook.');

$dropdown = $read('public/assets/admin/js/components/dropdown.js');
foreach ([
    '[data-dropdown]',
    '[data-dropdown-trigger]',
    '[data-dropdown-menu]',
    '[data-dropdown-item]',
    "aria-haspopup', 'menu",
    "aria-expanded', 'false",
    "event.key === 'Escape'",
    "event.key === 'ArrowDown'",
    "event.key === 'ArrowUp'",
] as $contract) {
    $assertContains($dropdown, $contract, 'Missing dropdown behavior contract: ' . $contract);
}

$assertContains(
    $dropdown,
    'scope = document',
    'Dropdown initializer must support scoped re-initialization for future partial updates.',
);

echo "Admin UI foundation smoke: OK\n";
