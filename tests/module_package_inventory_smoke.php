<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\ModulePackageInventoryRepository;
use Core\Extension\ModulePackageLifecycle;
use ZipArchive;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists(ZipArchive::class)) {
    fwrite(STDOUT, "MODULE PACKAGE INVENTORY SKIPPED (zip extension unavailable)\n");
    exit(0);
}

$db = Database::connection();
$root = sys_get_temp_dir() . '/uvcms-package-inventory-' . bin2hex(random_bytes(6));
$modules = $root . '/modules';
if (!mkdir($modules, 0775, true) && !is_dir($modules)) {
    throw new RuntimeException('Unable to create package inventory smoke root.');
}

$archive = $root . '/reviews.zip';
$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Unable to create package inventory smoke archive.');
}

$manifest = [
    'code' => 'inventory-smoke',
    'name' => 'Inventory Smoke',
    'version' => '1.2.3',
    'extension_api' => '^1.0',
    'default_enabled' => false,
    'requires' => ['core' => '>=0.1.0'],
    'provider' => 'Vendor\\InventorySmoke\\Module',
];
$zip->addFromString('module.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
$zip->addFromString('autoload.php', "<?php\n");
$zip->close();

$checksum = hash_file('sha256', $archive);
if (!is_string($checksum)) {
    throw new RuntimeException('Unable to calculate package checksum for smoke test.');
}

try {
    $installed = (new ModulePackageLifecycle($db, $root))->install($archive);
    if ($installed->code !== 'inventory-smoke' || $installed->version !== '1.2.3') {
        throw new RuntimeException('Package lifecycle returned the wrong manifest.');
    }

    $row = (new ModulePackageInventoryRepository($db))->find('inventory-smoke');
    if ($row === null
        || $row['version'] !== '1.2.3'
        || $row['package_sha256'] !== $checksum
        || $row['source'] !== 'package') {
        throw new RuntimeException('Installed package was not recorded correctly in inventory.');
    }

    if (!is_file($root . '/modules/inventory-smoke/module.json')) {
        throw new RuntimeException('Installed package files are missing after lifecycle install.');
    }
} finally {
    $db->exec("DELETE FROM module_package_inventory WHERE module_code = 'inventory-smoke'");

    $remove = static function (string $path) use (&$remove): void {
        if (!file_exists($path)) {
            return;
        }
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $item) {
                if ($item !== '.' && $item !== '..') {
                    $remove($path . '/' . $item);
                }
            }
            @rmdir($path);
            return;
        }
        @unlink($path);
    };
    $remove($root);
}

fwrite(STDOUT, "MODULE PACKAGE INVENTORY OK\n");
