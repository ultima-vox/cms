<?php

declare(strict_types=1);

use Core\Extension\ModuleLoader;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$manifests = (new ModuleLoader($root))->discover();
$byCode = [];
foreach ($manifests as $manifest) {
    $byCode[$manifest->code] = $manifest;
}

$infosystem = $byCode['infosystem'] ?? null;
if ($infosystem === null || $infosystem->version !== '1.0.0') {
    throw new RuntimeException('Infosystem module manifest is missing or invalid.');
}
if (($infosystem->requires['core'] ?? null) !== '>=0.1.0') {
    throw new RuntimeException('Core dependency was not parsed from infosystem manifest.');
}

$sites = $byCode['sites'] ?? null;
if ($sites === null || $sites->version !== '1.0.0') {
    throw new RuntimeException('Sites module manifest is missing or invalid.');
}
if (($sites->requires['core'] ?? null) !== '>=0.1.0') {
    throw new RuntimeException('Core dependency was not parsed from sites manifest.');
}

fwrite(STDOUT, "MODULE MANIFEST OK\n");
