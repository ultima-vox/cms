<?php

declare(strict_types=1);

use Core\Bootstrap\BuiltinExtensions;
use Core\Database;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Extension\ModuleManager;
use Core\Extension\ModuleManifest;
use Core\Extension\ModuleStateRepository;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$db = Database::connection();
$manager = new ModuleManager($db, $root);
$loader = new ModuleLoader($root);
$state = new ModuleStateRepository($db);

$rows = [];
foreach ($manager->listing() as $row) {
    $rows[$row['code']] = $row;
}
if (($rows['infosystem']['enabled'] ?? null) !== true
    || ($rows['sites']['enabled'] ?? null) !== true
    || ($rows['infosystem']['synchronized'] ?? null) !== true) {
    throw new RuntimeException('Initial synchronized module state is invalid.');
}

$manager->disable('infosystem');
$rows = [];
foreach ($manager->listing() as $row) {
    $rows[$row['code']] = $row;
}
if (($rows['infosystem']['enabled'] ?? null) !== false) {
    throw new RuntimeException('Module disable state was not persisted.');
}

$core = new ExtensionCore(new RuntimeApi($db, $root));
(new BuiltinExtensions())->register($core);
$loaded = $loader->load($core);
if (in_array('infosystem', $loaded, true) || !in_array('sites', $loaded, true)) {
    throw new RuntimeException('Disabled module was registered into runtime.');
}

$manager->sync();
$rows = [];
foreach ($manager->listing() as $row) {
    $rows[$row['code']] = $row;
}
if (($rows['infosystem']['enabled'] ?? null) !== false) {
    throw new RuntimeException('extensions:sync overwrote manual disabled state.');
}

$manager->enable('infosystem');
$core = new ExtensionCore(new RuntimeApi($db, $root));
(new BuiltinExtensions())->register($core);
$loaded = $loader->load($core);
if (!in_array('infosystem', $loaded, true) || !in_array('sites', $loaded, true)) {
    throw new RuntimeException('Enabled module was not restored into runtime.');
}

$base = new ModuleManifest(
    'fixture-base',
    'Fixture Base',
    '1.0.0',
    '^1.0',
    true,
    ['core' => '>=0.1.0'],
    stdClass::class,
    $root,
);
$dependent = new ModuleManifest(
    'fixture-dependent',
    'Fixture Dependent',
    '1.0.0',
    '^1.0',
    true,
    ['core' => '>=0.1.0', 'fixture-base' => '^1.0'],
    stdClass::class,
    $root,
);
$state->sync([$base, $dependent]);
try {
    $loader->assertCanDisable('fixture-base', [$base, $dependent], $state);
    throw new RuntimeException('Enabled dependency could be disabled.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Enabled dependency could be disabled.') {
        throw $exception;
    }
}

$incompatible = new ModuleManifest(
    'fixture-incompatible',
    'Fixture Incompatible',
    '1.0.0',
    '^9.0',
    false,
    ['core' => '>=0.1.0'],
    stdClass::class,
    $root,
);
$state->sync([$incompatible]);
if ($loader->enabledInLoadOrder([$incompatible], $state) !== []) {
    throw new RuntimeException('Disabled incompatible module entered load order.');
}
$state->setEnabled('fixture-incompatible', true);
try {
    $loader->assertCanEnable('fixture-incompatible', [$incompatible], $state);
    throw new RuntimeException('Incompatible Extension API was accepted.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Incompatible Extension API was accepted.') {
        throw $exception;
    }
}

$db->exec("DELETE FROM installed_modules WHERE code LIKE 'fixture-%'");
$manager->enable('infosystem');

fwrite(STDOUT, "MODULE LIFECYCLE OK\n");
