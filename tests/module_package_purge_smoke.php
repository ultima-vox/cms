<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\ModulePackageLifecycle;
use Core\Extension\ModulePackagePurger;
use Core\Extension\ModuleStateRepository;
use ZipArchive;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists(ZipArchive::class)) {
    fwrite(STDOUT, "MODULE PACKAGE PURGE SKIPPED (zip extension unavailable)\n");
    return;
}

$db = Database::connection();
$root = sys_get_temp_dir() . '/uvcms-package-purge-' . bin2hex(random_bytes(6));
if (!mkdir($root . '/modules', 0775, true) && !is_dir($root . '/modules')) {
    throw new RuntimeException('Unable to create package purge smoke root.');
}

$archive = $root . '/purge-smoke.zip';
$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Unable to create purge smoke package.');
}

$manifest = [
    'code' => 'purge-smoke',
    'name' => 'Purge Smoke',
    'version' => '1.0.0',
    'extension_api' => '^1.0',
    'default_enabled' => false,
    'requires' => ['core' => '>=0.1.0'],
    'provider' => 'Vendor\\PurgeSmoke\\Module',
    'purge' => ['purge/001_drop.sql'],
];
$zip->addFromString('module.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
$zip->addFromString('autoload.php', "<?php\n");
$zip->addFromString('purge/001_drop.sql', 'DROP TABLE purge_smoke_data;');
$zip->close();

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

try {
    $lifecycle = new ModulePackageLifecycle($db, $root);
    $installed = $lifecycle->install($archive);
    (new ModuleStateRepository($db))->sync([$installed]);

    $db->exec('CREATE TABLE purge_smoke_data (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
    $db->exec("INSERT INTO purge_smoke_data (id, value) VALUES (1, 'must disappear')");

    $migration = $db->prepare(
        'INSERT INTO module_schema_migrations (module_code, migration, checksum) VALUES (:module_code, :migration, :checksum)'
    );
    $migration->execute([
        'module_code' => 'purge-smoke',
        'migration' => '001_create.sql',
        'checksum' => str_repeat('a', 64),
    ]);

    try {
        (new ModulePackagePurger($db, $root))->purge('purge-smoke', 'wrong-confirmation');
        throw new RuntimeException('Purge accepted invalid confirmation.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Purge accepted invalid confirmation.') {
            throw $exception;
        }
    }

    if (!is_dir($root . '/modules/purge-smoke')) {
        throw new RuntimeException('Rejected purge changed module filesystem state.');
    }

    (new ModulePackagePurger($db, $root))->purge('purge-smoke', 'purge-smoke');

    if (is_dir($root . '/modules/purge-smoke')) {
        throw new RuntimeException('Purged module code still exists.');
    }
    if ($db->query("SELECT to_regclass('public.purge_smoke_data')")->fetchColumn() !== null) {
        throw new RuntimeException('Purged module business table still exists.');
    }
    if ((int) $db->query("SELECT COUNT(*) FROM module_schema_migrations WHERE module_code = 'purge-smoke'")->fetchColumn() !== 0) {
        throw new RuntimeException('Purged module migration history still exists.');
    }
    if ((int) $db->query("SELECT COUNT(*) FROM module_package_inventory WHERE module_code = 'purge-smoke'")->fetchColumn() !== 0) {
        throw new RuntimeException('Purged module inventory still exists.');
    }
    if ((int) $db->query("SELECT COUNT(*) FROM installed_modules WHERE code = 'purge-smoke'")->fetchColumn() !== 0) {
        throw new RuntimeException('Purged module state still exists.');
    }
} finally {
    $db->exec('DROP TABLE IF EXISTS purge_smoke_data');
    $db->exec("DELETE FROM module_schema_migrations WHERE module_code = 'purge-smoke'");
    $db->exec("DELETE FROM installed_modules WHERE code = 'purge-smoke'");
    $db->exec("DELETE FROM module_package_inventory WHERE module_code = 'purge-smoke'");
    $remove($root);
}

fwrite(STDOUT, "MODULE PACKAGE PURGE OK\n");
