<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\ModuleMigrationCoordinator;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = Database::connection();
$fixtureRoot = dirname(__DIR__) . '/storage/tmp/module-migration-coordinator-' . bin2hex(random_bytes(6));
$modulesRoot = $fixtureRoot . '/modules';

$writeModule = static function (
    string $root,
    string $code,
    bool $enabled,
    array $requires,
    string $sql,
): void {
    $directory = $root . '/' . $code;
    if (!mkdir($directory . '/migrations', 0775, true) && !is_dir($directory . '/migrations')) {
        throw new RuntimeException('Unable to create fixture module directory.');
    }

    $manifest = [
        'code' => $code,
        'name' => $code,
        'version' => '1.0.0',
        'extension_api' => '^1.0',
        'default_enabled' => $enabled,
        'requires' => $requires,
        'provider' => stdClass::class,
    ];
    file_put_contents(
        $directory . '/module.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    );
    file_put_contents($directory . '/migrations/001_fixture.sql', $sql);
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (new FilesystemIterator($path) as $item) {
        if ($item->isDir() && !$item->isLink()) {
            $removeTree($item->getPathname());
            continue;
        }
        unlink($item->getPathname());
    }

    rmdir($path);
};

try {
    $writeModule(
        $modulesRoot,
        'fixture-base',
        true,
        ['core' => '>=0.1.0'],
        "CREATE TABLE fixture_migration_base (id INTEGER PRIMARY KEY);",
    );
    $writeModule(
        $modulesRoot,
        'fixture-dependent',
        true,
        ['core' => '>=0.1.0', 'fixture-base' => '^1.0'],
        "CREATE TABLE fixture_migration_dependent (
            id INTEGER PRIMARY KEY,
            base_id INTEGER REFERENCES fixture_migration_base(id)
        );",
    );
    $writeModule(
        $modulesRoot,
        'fixture-disabled',
        false,
        ['core' => '>=0.1.0'],
        "CREATE TABLE fixture_migration_disabled (id INTEGER PRIMARY KEY);",
    );

    $coordinator = new ModuleMigrationCoordinator($db, $fixtureRoot);
    $result = $coordinator->migrateEnabled();

    if (array_keys($result) !== ['fixture-base', 'fixture-dependent']) {
        throw new RuntimeException('Enabled module migrations were not applied in dependency order.');
    }
    if ($result['fixture-base'] !== ['001_fixture.sql']
        || $result['fixture-dependent'] !== ['001_fixture.sql']) {
        throw new RuntimeException('Coordinator returned unexpected migration results.');
    }

    if ($db->query("SELECT to_regclass('public.fixture_migration_base')")->fetchColumn()
        !== 'fixture_migration_base'
        || $db->query("SELECT to_regclass('public.fixture_migration_dependent')")->fetchColumn()
        !== 'fixture_migration_dependent') {
        throw new RuntimeException('Enabled fixture module schema was not created.');
    }
    if ($db->query("SELECT to_regclass('public.fixture_migration_disabled')")->fetchColumn() !== null) {
        throw new RuntimeException('Disabled module migration was applied by --all coordinator.');
    }

    if ($coordinator->migrateEnabled() !== []) {
        throw new RuntimeException('Enabled module migration coordinator is not idempotent.');
    }
} finally {
    $db->exec('DROP TABLE IF EXISTS fixture_migration_dependent');
    $db->exec('DROP TABLE IF EXISTS fixture_migration_base');
    $db->exec('DROP TABLE IF EXISTS fixture_migration_disabled');
    $db->exec("DELETE FROM module_schema_migrations WHERE module_code LIKE 'fixture-%'");
    $db->exec("DELETE FROM installed_modules WHERE code LIKE 'fixture-%'");
    $removeTree($fixtureRoot);
}

fwrite(STDOUT, "MODULE MIGRATION COORDINATOR OK\n");
