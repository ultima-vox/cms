<?php

declare(strict_types=1);

use Core\Database;
use Core\MigrationRunner;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$db = Database::connection();
$runner = new MigrationRunner($db, $root . '/database/migrations');
$runner->migrate();

$row = $db->query(
    "SELECT migration, checksum FROM schema_migrations ORDER BY migration LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!is_array($row) || !is_string($row['checksum'] ?? null) || strlen(trim($row['checksum'])) !== 64) {
    throw new RuntimeException('Migration checksum was not recorded.');
}

$migration = (string) $row['migration'];
$original = trim((string) $row['checksum']);
$statement = $db->prepare('UPDATE schema_migrations SET checksum = :checksum WHERE migration = :migration');
$statement->execute([
    'migration' => $migration,
    'checksum' => str_repeat('0', 64),
]);

try {
    $runner->migrate();
    throw new RuntimeException('Modified applied migration was not rejected.');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'была изменена после применения')) {
        throw $exception;
    }
} finally {
    $statement->execute([
        'migration' => $migration,
        'checksum' => $original,
    ]);
}

$runner->migrate();
require __DIR__ . '/layout_hierarchy_smoke.php';
require __DIR__ . '/layout_code_smoke.php';
require __DIR__ . '/node_page_type_schema_smoke.php';
require __DIR__ . '/module_migration_smoke.php';
require __DIR__ . '/module_package_inventory_smoke.php';
require __DIR__ . '/module_package_purge_smoke.php';
require __DIR__ . '/installer_state_smoke.php';
require __DIR__ . '/installer_database_smoke.php';
fwrite(STDOUT, "MIGRATION CHECKSUM OK\n");
