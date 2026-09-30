<?php

declare(strict_types=1);

use Core\Extension\ExtensionApiVersion;
use Core\Extension\ModuleLoader;
use Core\Extension\VersionConstraint;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$manifests = (new ModuleLoader($root))->discover();
$byCode = [];
foreach ($manifests as $manifest) {
    $byCode[$manifest->code] = $manifest;
}

foreach (['infosystem', 'sites'] as $code) {
    $manifest = $byCode[$code] ?? null;
    if ($manifest === null || $manifest->version !== '1.0.0') {
        throw new RuntimeException(sprintf('%s module manifest is missing or invalid.', $code));
    }
    if (($manifest->requires['core'] ?? null) !== '>=0.1.0') {
        throw new RuntimeException(sprintf('Core dependency was not parsed from %s manifest.', $code));
    }
    if ($manifest->extensionApi !== '^1.0' || !$manifest->defaultEnabled) {
        throw new RuntimeException(sprintf('Lifecycle metadata is invalid for %s.', $code));
    }
    if (!VersionConstraint::matches(ExtensionApiVersion::VERSION, $manifest->extensionApi)) {
        throw new RuntimeException(sprintf('Extension API constraint does not match for %s.', $code));
    }
}

if (!VersionConstraint::matches('1.4.2', '^1.0')
    || !VersionConstraint::matches('1.4.2', '>=1.2 <2.0')
    || VersionConstraint::matches('2.0.0', '^1.0')) {
    throw new RuntimeException('Version constraint semantics failed.');
}

fwrite(STDOUT, "MODULE MANIFEST OK\n");
