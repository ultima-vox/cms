<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\ModuleMigrationRunner;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$db = Database::connection();
$runner = new ModuleMigrationRunner($db, $root);

$applied = $runner->migrate('infosystem');
if ($applied !== ['001_public_delivery_index.sql']) {
    throw new RuntimeException('Unexpected infosystem module migration result.');
}

if ($runner->migrate('infosystem') !== []) {
    throw new RuntimeException('Applied module migration was executed twice.');
}

$index = $db->query(
    "SELECT to_regclass('public.idx_infosystem_items_public_delivery')"
)->fetchColumn();
if ($index !== 'idx_infosystem_items_public_delivery') {
    throw new RuntimeException('Infosystem-owned migration did not create its index.');
}

$statement = $db->prepare(
    <<<'SQL'
    SELECT checksum
    FROM module_schema_migrations
    WHERE module_code = 'infosystem'
      AND migration = '001_public_delivery_index.sql'
    SQL
);
$statement->execute();
$original = $statement->fetchColumn();
if (!is_string($original) || strlen(trim($original)) !== 64) {
    throw new RuntimeException('Module migration checksum was not recorded.');
}
$original = trim($original);

$update = $db->prepare(
    <<<'SQL'
    UPDATE module_schema_migrations
    SET checksum = :checksum
    WHERE module_code = 'infosystem'
      AND migration = '001_public_delivery_index.sql'
    SQL
);
$update->execute(['checksum' => str_repeat('0', 64)]);

try {
    $runner->migrate('infosystem');
    throw new RuntimeException('Modified applied module migration was not rejected.');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'была изменена после применения')) {
        throw $exception;
    }
} finally {
    $update->execute(['checksum' => $original]);
}

try {
    $runner->migrate('does-not-exist');
    throw new RuntimeException('Unknown module migration request was accepted.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Unknown module migration request was accepted.') {
        throw $exception;
    }
}

fwrite(STDOUT, "MODULE MIGRATIONS OK\n");
