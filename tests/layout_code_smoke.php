<?php

declare(strict_types=1);

use Core\Database;
use Core\Repository\LayoutRepository;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$db = Database::connection();
$repository = new LayoutRepository($db);

$main = $repository->findByCode('main');
if ($main === null
    || (string) ($main['template_path'] ?? '') !== 'layouts/main.php'
    || ($main['code'] ?? null) !== 'main') {
    throw new RuntimeException('System layout code was not backfilled correctly.');
}

$db->beginTransaction();

try {
    $id = $repository->create(
        'Layout code smoke',
        'layouts/layout-code-smoke.php',
        'Stable layout code smoke test',
    );

    $layout = $repository->findByCode('layout-code-smoke');
    if ($layout === null
        || (int) ($layout['id'] ?? 0) !== $id
        || (string) ($layout['template_path'] ?? '') !== 'layouts/layout-code-smoke.php') {
        throw new RuntimeException('Layout repository did not persist or resolve the stable code.');
    }

    try {
        $repository->create(
            'Duplicate layout code smoke',
            'layouts/layout-code-smoke.html.php',
            null,
        );
        throw new RuntimeException('Duplicate layout code was accepted.');
    } catch (Throwable $exception) {
        if ($exception->getMessage() === 'Duplicate layout code was accepted.') {
            throw $exception;
        }
    }

    try {
        $repository->create('Unsupported layout', 'layouts/unsupported.twig', null);
        throw new RuntimeException('Unsupported template engine was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Layout template path cannot be converted to a stable code.') {
            throw $exception;
        }
    }

    try {
        $repository->findByCode('../main');
        throw new RuntimeException('Invalid layout code was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Invalid layout code was accepted.') {
            throw $exception;
        }
    }
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

fwrite(STDOUT, "LAYOUT CODE OK\n");
