<?php

declare(strict_types=1);

use Core\Database;
use Core\Repository\NodeModuleBindingRepository;
use RuntimeException;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = Database::connection();
$bindings = new NodeModuleBindingRepository($db);

$legacyColumn = (int) $db->query(
    <<<'SQL'
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'nodes'
      AND column_name = 'infosystem_id'
    SQL
)->fetchColumn();
if ($legacyColumn !== 0) {
    throw new RuntimeException('Legacy nodes.infosystem_id still exists.');
}

$rootId = (int) $db->query("SELECT id FROM nodes WHERE site_id = 1 AND path = '/'")->fetchColumn();
$aboutId = (int) $db->query("SELECT id FROM nodes WHERE site_id = 1 AND path = '/about'")->fetchColumn();
$secondRootId = (int) $db->query(
    "SELECT n.id FROM nodes n JOIN sites s ON s.id = n.site_id WHERE s.code = 'second' AND n.path = '/'"
)->fetchColumn();

if ($rootId < 1 || $aboutId < 1 || $secondRootId < 1) {
    throw new RuntimeException('Binding smoke-test nodes are missing.');
}

if ($bindings->findTargetKey($rootId, 'infosystem', 'primary') !== 'catalog') {
    throw new RuntimeException('Default node binding was not seeded correctly.');
}

$original = $bindings->nodeIdsForTarget(1, 'infosystem', 'primary', 'catalog');
if ($original !== [$rootId]) {
    throw new RuntimeException('Unexpected initial binding set.');
}

try {
    $bindings->replaceTargetNodes(1, 'infosystem', 'primary', 'catalog', [$rootId, $aboutId]);
    $updated = $bindings->nodeIdsForTarget(1, 'infosystem', 'primary', 'catalog');
    sort($updated, SORT_NUMERIC);
    $expected = [$rootId, $aboutId];
    sort($expected, SORT_NUMERIC);
    if ($updated !== $expected) {
        throw new RuntimeException('Generic binding replacement failed.');
    }

    try {
        $bindings->replaceTargetNodes(1, 'infosystem', 'primary', 'catalog', [$secondRootId]);
        throw new RuntimeException('Cross-site node binding was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Cross-site node binding was accepted.') {
            throw $exception;
        }
    }
} finally {
    $bindings->replaceTargetNodes(1, 'infosystem', 'primary', 'catalog', $original);
}

fwrite(STDOUT, "NODE MODULE BINDING OK\n");
