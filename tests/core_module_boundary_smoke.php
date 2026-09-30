<?php

declare(strict_types=1);

use Core\Controller\NodeController;

require dirname(__DIR__) . '/vendor/autoload.php';

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

$source = file_get_contents(dirname(__DIR__) . '/core/Controller/NodeController.php');
if ($source === false) {
    throw new RuntimeException('Unable to read NodeController source.');
}

foreach (['InfosystemRepository', "'infosystem_id'", '"infosystem_id"'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        throw new RuntimeException('NodeController contains module-specific coupling: ' . $forbidden);
    }
}

fwrite(STDOUT, "CORE MODULE BOUNDARY OK\n");
