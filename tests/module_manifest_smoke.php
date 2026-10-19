<?php

declare(strict_types=1);

use Core\Extension\ExtensionApiVersion;
use Core\Extension\ModuleLoader;
use Core\Extension\ModuleManifest;
use Core\Extension\ModuleManifestReader;
use Core\Extension\VersionConstraint;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$manifests = (new ModuleLoader($root))->discover();
$byCode = [];
foreach ($manifests as $manifest) {
    $byCode[$manifest->code] = $manifest;
}

foreach (['documents' => '1.3.1', 'infosystem' => '1.2.0', 'sites' => '1.2.0'] as $code => $version) {
    $manifest = $byCode[$code] ?? null;
    if ($manifest === null || $manifest->version !== $version) {
        throw new RuntimeException(sprintf('%s module manifest is missing or invalid.', $code));
    }
    if (($manifest->requires['core'] ?? null) !== '>=0.4.0') {
        throw new RuntimeException(sprintf('Core dependency was not parsed from %s manifest.', $code));
    }
    if ($manifest->extensionApi !== '^1.0' || !$manifest->defaultEnabled) {
        throw new RuntimeException(sprintf('Lifecycle metadata is invalid for %s.', $code));
    }
    if (!VersionConstraint::matches(ExtensionApiVersion::VERSION, $manifest->extensionApi)) {
        throw new RuntimeException(sprintf('Extension API constraint does not match for %s.', $code));
    }
}

foreach (['documents', 'infosystem'] as $code) {
    if (!is_file($root . '/modules/' . $code . '/module.json')) {
        throw new RuntimeException(sprintf('%s static module.json manifest is missing.', $code));
    }
}

if ($byCode['documents']->migrationAfter !== ['infosystem']
    || isset($byCode['documents']->requires['infosystem'])) {
    throw new RuntimeException('Migration ordering leaked into runtime dependencies.');
}
foreach (['infosystem', ['bad/path'], ['documents'], ['core'], ['infosystem', 'infosystem'], ['key' => 'infosystem'], [1]] as $invalid) {
    try {
        ModuleManifest::fromArray([
            'code' => 'documents',
            'name' => 'Documents',
            'version' => '1.0.0',
            'extension_api' => '^1.0',
            'provider' => stdClass::class,
            'migration_after' => $invalid,
        ], __DIR__);
        throw new RuntimeException('Invalid migration_after was accepted.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'migration_after')) {
            throw $exception;
        }
        if ($exception->getMessage() === 'Invalid migration_after was accepted.') {
            throw $exception;
        }
    }
}

$temp = sys_get_temp_dir() . '/uvcms-static-manifest-' . bin2hex(random_bytes(6));
if (!mkdir($temp, 0775, true) && !is_dir($temp)) {
    throw new RuntimeException('Unable to create static manifest smoke directory.');
}

try {
    file_put_contents($temp . '/module.json', json_encode([
        'code' => 'static-test',
        'name' => 'Static Test',
        'version' => '1.2.3',
        'extension_api' => '^1.0',
        'default_enabled' => false,
        'requires' => ['core' => '>=0.1.0'],
        'provider' => 'Vendor\\StaticTest\\Module',
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    file_put_contents(
        $temp . '/module.php',
        "<?php throw new RuntimeException('module.php must not execute when module.json exists');",
    );

    $static = (new ModuleManifestReader())->read($temp);
    if ($static === null
        || $static->code !== 'static-test'
        || $static->version !== '1.2.3'
        || $static->provider !== 'Vendor\\StaticTest\\Module') {
        throw new RuntimeException('Static module manifest was not parsed correctly.');
    }
} finally {
    @unlink($temp . '/module.json');
    @unlink($temp . '/module.php');
    @rmdir($temp);
}

if (!VersionConstraint::matches('1.4.2', '^1.0')
    || !VersionConstraint::matches('1.4.2', '>=1.2 <2.0')
    || VersionConstraint::matches('2.0.0', '^1.0')) {
    throw new RuntimeException('Version constraint semantics failed.');
}

fwrite(STDOUT, "MODULE MANIFEST OK\n");
