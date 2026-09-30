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

$source = file_get_contents($root . '/core/Controller/NodeController.php');
if ($source === false) {
    throw new RuntimeException('Unable to read NodeController source.');
}

foreach (['InfosystemRepository', "'infosystem_id'", '"infosystem_id"'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        throw new RuntimeException('NodeController contains module-specific coupling: ' . $forbidden);
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

fwrite(STDOUT, "CORE MODULE BOUNDARY OK\n");
