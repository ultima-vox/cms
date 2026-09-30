<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\ModulePackageInventoryRepository;
use Core\Extension\ModulePackageLifecycle;
use Core\Extension\ModuleStateRepository;
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

$makePackage = static function (string $path, string $version, string $marker): string {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create package inventory smoke archive.');
    }

    $manifest = [
        'code' => 'inventory-smoke',
        'name' => 'Inventory Smoke',
        'version' => $version,
        'extension_api' => '^1.0',
        'default_enabled' => false,
        'requires' => ['core' => '>=0.1.0'],
        'provider' => 'Vendor\\InventorySmoke\\Module',
    ];
    $zip->addFromString('module.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    $zip->addFromString('autoload.php', "<?php\n");
    $zip->addFromString('VERSION.txt', $marker);
    $zip->close();

    $checksum = hash_file('sha256', $path);
    if (!is_string($checksum)) {
        throw new RuntimeException('Unable to calculate package checksum for smoke test.');
    }

    return $checksum;
};

$archive = $root . '/inventory-1.2.3.zip';
$checksum = $makePackage($archive, '1.2.3', 'old');

try {
    $lifecycle = new ModulePackageLifecycle($db, $root);
    $installed = $lifecycle->install($archive);
    if ($installed->code !== 'inventory-smoke' || $installed->version !== '1.2.3') {
        throw new RuntimeException('Package lifecycle returned the wrong manifest.');
    }

    $inventory = new ModulePackageInventoryRepository($db);
    $row = $inventory->find('inventory-smoke');
    if ($row === null
        || $row['version'] !== '1.2.3'
        || $row['package_sha256'] !== $checksum
        || $row['source'] !== 'package') {
        throw new RuntimeException('Installed package was not recorded correctly in inventory.');
    }

    if (trim((string) file_get_contents($root . '/modules/inventory-smoke/VERSION.txt')) !== 'old') {
        throw new RuntimeException('Initial package files are missing after lifecycle install.');
    }

    $state = new ModuleStateRepository($db);
    $state->sync([$installed]);

    $updateArchive = $root . '/inventory-1.3.0.zip';
    $updateChecksum = $makePackage($updateArchive, '1.3.0', 'new');
    $updated = $lifecycle->update($updateArchive);
    if ($updated->version !== '1.3.0') {
        throw new RuntimeException('Package update returned the wrong manifest.');
    }

    $updatedRow = $inventory->find('inventory-smoke');
    if ($updatedRow === null
        || $updatedRow['version'] !== '1.3.0'
        || $updatedRow['package_sha256'] !== $updateChecksum) {
        throw new RuntimeException('Package update did not refresh inventory metadata.');
    }
    if (trim((string) file_get_contents($root . '/modules/inventory-smoke/VERSION.txt')) !== 'new') {
        throw new RuntimeException('Updated package files were not activated.');
    }

    try {
        $lifecycle->update($updateArchive);
        throw new RuntimeException('Same-version package update was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Same-version package update was accepted.') {
            throw $exception;
        }
    }

    $state->setEnabled('inventory-smoke', true);
    $enabledArchive = $root . '/inventory-1.4.0.zip';
    $makePackage($enabledArchive, '1.4.0', 'enabled-block');
    try {
        $lifecycle->update($enabledArchive);
        throw new RuntimeException('Enabled module package update was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Enabled module package update was accepted.') {
            throw $exception;
        }
    } finally {
        $state->setEnabled('inventory-smoke', false);
    }

    if (trim((string) file_get_contents($root . '/modules/inventory-smoke/VERSION.txt')) !== 'new') {
        throw new RuntimeException('Rejected package update changed active module files.');
    }
} finally {
    $db->exec("DELETE FROM installed_modules WHERE code = 'inventory-smoke'");
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
