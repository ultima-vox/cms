<?php

declare(strict_types=1);

use Core\Controller\NodeController;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$constructor = (new ReflectionClass(NodeController::class))->getConstructor();
if ($constructor === null) {
    throw new RuntimeException('NodeController constructor is missing.');
}

foreach ($constructor->getParameters() as $parameter) {
    $type = $parameter->getType();
    if (!$type instanceof ReflectionNamedType) {
        continue;
    }

    if (str_contains(strtolower($type->getName()), 'infosystem')) {
        throw new RuntimeException(
            sprintf(
                'Core public rendering must not depend on infosystem services; parameter $%s uses %s.',
                $parameter->getName(),
                $type->getName(),
            ),
        );
    }
}

$coreIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/core', RecursiveDirectoryIterator::SKIP_DOTS),
);

/** @var SplFileInfo $coreFile */
foreach ($coreIterator as $coreFile) {
    if (!$coreFile->isFile() || strtolower($coreFile->getExtension()) !== 'php') {
        continue;
    }

    $source = file_get_contents($coreFile->getPathname());
    if ($source === false) {
        throw new RuntimeException('Unable to read Core source: ' . $coreFile->getPathname());
    }

    if (str_contains($source, 'UltimaVox\\Modules\\')) {
        throw new RuntimeException(
            'Core must not import business module namespaces: ' . $coreFile->getPathname(),
        );
    }
}

$coreSources = [
    'core/Controller/NodeController.php',
    'core/Controller/StructureController.php',
    'core/Repository/NodeRepository.php',
    'core/Repository/StructureRepository.php',
];

foreach ($coreSources as $path) {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        throw new RuntimeException('Unable to read Core source: ' . $path);
    }

    foreach (['InfosystemRepository', 'infosystem_id', 'infosystem_name', 'function infosystems'] as $forbidden) {
        if (str_contains($source, $forbidden)) {
            throw new RuntimeException($path . ' contains module-specific coupling: ' . $forbidden);
        }
    }
}

$structureTemplate = file_get_contents($root . '/templates/admin/structure/form.twig');
if ($structureTemplate === false) {
    throw new RuntimeException('Unable to read structure form template.');
}
foreach (['name="infosystem_id"', 'node.infosystem_id', 'infosystem_name'] as $forbidden) {
    if (str_contains($structureTemplate, $forbidden)) {
        throw new RuntimeException('Core structure UI contains infosystem coupling: ' . $forbidden);
    }
}

$forbiddenCoreFiles = [
    'core/Controller/InfosystemController.php',
    'core/Controller/InfosystemItemListController.php',
    'core/Repository/InfosystemRepository.php',
    'core/Repository/InfosystemManagementRepository.php',
    'core/Repository/InfosystemItemSearchRepository.php',
    'core/Infosystem/FieldSchema.php',
];

foreach ($forbiddenCoreFiles as $path) {
    if (is_file($root . '/' . $path)) {
        throw new RuntimeException('Infosystem domain implementation leaked into Core: ' . $path);
    }
}

$requiredModuleFiles = [
    'modules/infosystem/src/Admin/InfosystemController.php',
    'modules/infosystem/src/Admin/InfosystemItemListController.php',
    'modules/infosystem/src/Admin/InfosystemBindingController.php',
    'modules/infosystem/src/Repository/InfosystemRepository.php',
    'modules/infosystem/src/Repository/InfosystemManagementRepository.php',
    'modules/infosystem/src/Repository/InfosystemItemSearchRepository.php',
    'modules/infosystem/src/FieldSchema.php',
];

foreach ($requiredModuleFiles as $path) {
    if (!is_file($root . '/' . $path)) {
        throw new RuntimeException('Infosystem module does not own expected domain file: ' . $path);
    }
}

require __DIR__ . '/page_execution_registry_smoke.php';

fwrite(STDOUT, "CORE MODULE BOUNDARY OK\n");
