<?php

declare(strict_types=1);

use Core\Bootstrap\RuntimeFactory;
use Core\Database;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Extension\ModuleManager;
use Core\Extension\VersionConstraint;

$rootPath = dirname(__DIR__);
require $rootPath . '/vendor/autoload.php';

$versionCases = [
    ['1.4.2', '^1.0', true],
    ['2.0.0', '^1.0', false],
    ['0.2.9', '^0.2.1', true],
    ['0.3.0', '^0.2.1', false],
    ['1.9.0', '~1.2', true],
    ['2.0.0', '~1.2', false],
    ['1.2.9', '~1.2.3', true],
    ['1.3.0', '~1.2.3', false],
    ['1.7.3', '>=1.5 <2.0', true],
    ['2.1.0', '>=1.5 <2.0', false],
    ['2.1.0', '^1.0 || ^2.0', true],
    ['1.2.3', '1.2.*', true],
    ['1.3.0', '1.2.*', false],
];

foreach ($versionCases as [$version, $constraint, $expected]) {
    if (VersionConstraint::matches($version, $constraint) !== $expected) {
        throw new RuntimeException(sprintf('Version constraint failed: %s %s.', $version, $constraint));
    }
}

$db = Database::connection();
$manager = new ModuleManager($db, $rootPath);
$listing = $manager->listing();
$infosystem = null;

foreach ($listing as $row) {
    if ($row['code'] === 'infosystem') {
        $infosystem = $row;
        break;
    }
}

if ($infosystem === null || !$infosystem['synchronized'] || !$infosystem['enabled']) {
    throw new RuntimeException('Infosystem module state was not synchronized as enabled.');
}

$manager->disable('infosystem');
$disabledCore = new ExtensionCore((new RuntimeFactory())->create($db, $rootPath));
$disabledLoaded = (new ModuleLoader($rootPath))->load($disabledCore);
if (in_array('infosystem', $disabledLoaded, true)) {
    throw new RuntimeException('Disabled infosystem module was still loaded.');
}

$manager->enable('infosystem');
$enabledCore = new ExtensionCore((new RuntimeFactory())->create($db, $rootPath));
$enabledLoaded = (new ModuleLoader($rootPath))->load($enabledCore);
if (!in_array('infosystem', $enabledLoaded, true)) {
    throw new RuntimeException('Re-enabled infosystem module was not loaded.');
}

$routeNames = array_map(static fn ($route): string => $route->name, $enabledCore->routes()->definitions());
if (!in_array('infosystem.index', $routeNames, true)) {
    throw new RuntimeException('Infosystem admin routes are not owned by the module runtime.');
}

fwrite(STDOUT, "MODULE LIFECYCLE OK\n");
