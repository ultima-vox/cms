<?php

declare(strict_types=1);

use Core\Database;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$db = Database::connection();

$columns = $db->query(
    <<<'SQL'
    SELECT column_name, column_default, is_nullable, data_type
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'nodes'
      AND column_name IN ('page_type', 'page_config')
    ORDER BY column_name
    SQL
)->fetchAll(PDO::FETCH_ASSOC);

if (!is_array($columns) || count($columns) !== 2) {
    throw new RuntimeException('Node page type columns are missing.');
}

$byName = [];
foreach ($columns as $column) {
    $byName[(string) $column['column_name']] = $column;
}

$pageType = $byName['page_type'] ?? null;
if (!is_array($pageType)
    || ($pageType['is_nullable'] ?? null) !== 'NO'
    || ($pageType['column_default'] ?? null) !== null) {
    throw new RuntimeException('nodes.page_type must be required without a database default.');
}

$pageConfig = $byName['page_config'] ?? null;
if (!is_array($pageConfig)
    || ($pageConfig['is_nullable'] ?? null) !== 'NO'
    || ($pageConfig['data_type'] ?? null) !== 'jsonb') {
    throw new RuntimeException('nodes.page_config schema is invalid.');
}

$constraints = $db->query(
    <<<'SQL'
    SELECT conname
    FROM pg_constraint
    WHERE conrelid = 'nodes'::regclass
      AND conname IN ('nodes_page_type_format', 'nodes_page_config_object')
    ORDER BY conname
    SQL
)->fetchAll(PDO::FETCH_COLUMN);

if ($constraints !== ['nodes_page_config_object', 'nodes_page_type_format']) {
    throw new RuntimeException('Node page type constraints are missing.');
}

fwrite(STDOUT, "NODE PAGE TYPE SCHEMA OK\n");
