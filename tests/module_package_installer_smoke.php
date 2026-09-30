<?php

declare(strict_types=1);

use Core\Extension\ModulePackageInstaller;
use ZipArchive;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists(ZipArchive::class)) {
    throw new RuntimeException('ZIP extension is required for module package installer smoke test.');
}

$workspace = sys_get_temp_dir() . '/uvcms-package-' . bin2hex(random_bytes(6));
$root = $workspace . '/cms';
$packages = $workspace . '/packages';

if (!mkdir($root . '/modules', 0775, true) || !mkdir($packages, 0775, true)) {
    throw new RuntimeException('Unable to create package installer smoke workspace.');
}

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . '/' . $item;
        if (is_dir($child) && !is_link($child)) {
            $removeTree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
};

$createPackage = static function (string $path, array $manifest, array $files = []): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create ZIP fixture: ' . $path);
    }

    try {
        $json = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!$zip->addFromString('module.json', $json)) {
            throw new RuntimeException('Unable to add module.json to ZIP fixture.');
        }
        foreach ($files as $name => $content) {
            if (!$zip->addFromString((string) $name, (string) $content)) {
                throw new RuntimeException('Unable to add ZIP fixture entry: ' . $name);
            }
        }
    } finally {
        $zip->close();
    }
};

$baseManifest = [
    'code' => 'package-test',
    'name' => 'Package Test',
    'version' => '1.0.0',
    'extension_api' => '^1.0',
    'default_enabled' => false,
    'requires' => ['core' => '>=0.1.0'],
    'provider' => 'Vendor\\PackageTest\\PackageTestModule',
];

try {
    $valid = $packages . '/valid.zip';
    $createPackage($valid, $baseManifest, [
        'autoload.php' => "<?php\n\ndeclare(strict_types=1);\n",
        'src/PackageTestModule.php' => "<?php\n\ndeclare(strict_types=1);\n",
        'README.md' => "Package test\n",
    ]);

    $manifest = (new ModulePackageInstaller($root))->install($valid);
    if ($manifest->code !== 'package-test' || $manifest->version !== '1.0.0' || $manifest->defaultEnabled) {
        throw new RuntimeException('Installed package manifest is invalid.');
    }
    if (!is_file($root . '/modules/package-test/module.json')
        || !is_file($root . '/modules/package-test/src/PackageTestModule.php')) {
        throw new RuntimeException('Verified package files were not installed.');
    }

    try {
        (new ModulePackageInstaller($root))->install($valid);
        throw new RuntimeException('Installer accepted an overwrite of an existing module.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'already installed')) {
            throw $exception;
        }
    }

    $autoEnabled = $packages . '/auto-enabled.zip';
    $unsafeManifest = $baseManifest;
    $unsafeManifest['code'] = 'unsafe-auto';
    $unsafeManifest['default_enabled'] = true;
    $createPackage($autoEnabled, $unsafeManifest);

    try {
        (new ModulePackageInstaller($root))->install($autoEnabled);
        throw new RuntimeException('Installer accepted default_enabled=true package.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'default_enabled=false')) {
            throw $exception;
        }
    }
    if (file_exists($root . '/modules/unsafe-auto')) {
        throw new RuntimeException('Rejected auto-enabled package left installed files behind.');
    }

    $traversal = $packages . '/traversal.zip';
    $traversalManifest = $baseManifest;
    $traversalManifest['code'] = 'unsafe-path';
    $createPackage($traversal, $traversalManifest, ['../escape.php' => '<?php']);

    try {
        (new ModulePackageInstaller($root))->install($traversal);
        throw new RuntimeException('Installer accepted a path traversal ZIP entry.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'path traversal')) {
            throw $exception;
        }
    }
    if (file_exists($root . '/escape.php') || file_exists($workspace . '/escape.php')) {
        throw new RuntimeException('Path traversal package wrote outside staging.');
    }

    $missingManifest = $packages . '/missing-manifest.zip';
    $zip = new ZipArchive();
    if ($zip->open($missingManifest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create missing-manifest fixture.');
    }
    $zip->addFromString('README.md', 'missing manifest');
    $zip->close();

    try {
        (new ModulePackageInstaller($root))->install($missingManifest);
        throw new RuntimeException('Installer accepted a package without module.json.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'module.json')) {
            throw $exception;
        }
    }

    fwrite(STDOUT, "MODULE PACKAGE INSTALLER OK\n");
} finally {
    $removeTree($workspace);
}
