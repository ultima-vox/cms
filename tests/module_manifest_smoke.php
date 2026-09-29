<?php

declare(strict_types=1);

use Core\Extension\ModuleLoader;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$manifests = (new ModuleLoader($root))->discover();

$infosystem = null;
foreach ($manifests as $manifest) {
    if ($manifest->code === 'infosystem') {
        $infosystem = $manifest;
        break;
    }
}

if ($infosystem === null) {
    throw new RuntimeException('Infosystem module manifest was not discovered.');
}
if ($infosystem->version !== '1.0.0') {
    throw new RuntimeException('Unexpected infosystem module version.');
}
if (($infosystem->requires['core'] ?? null) !== '>=0.1.0') {
    throw new RuntimeException('Core dependency was not parsed from the module manifest.');
}

fwrite(STDOUT, "MODULE MANIFEST OK\n");
