<?php

declare(strict_types=1);

use Core\Database;
use Core\Layout\LayoutHierarchyResolver;
use Core\Repository\LayoutRepository;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$db = Database::connection();
$repository = new LayoutRepository($db);
$resolver = new LayoutHierarchyResolver($repository);

$db->beginTransaction();

try {
    $insert = $db->prepare(
        <<<'SQL'
        INSERT INTO layouts (parent_id, code, name, template_path, description)
        VALUES (:parent_id, :code, :name, :template_path, 'Hierarchy smoke test')
        RETURNING id
        SQL
    );

    $createLayout = static function (
        ?int $parentId,
        string $code,
        string $name,
        string $templatePath,
    ) use ($insert): int {
        $insert->execute([
            'parent_id' => $parentId,
            'code' => $code,
            'name' => $name,
            'template_path' => $templatePath,
        ]);

        return (int) $insert->fetchColumn();
    };

    $outerId = $createLayout(null, 'smoke-outer', 'Smoke outer', 'layouts/smoke-outer.php');
    $middleId = $createLayout($outerId, 'smoke-middle', 'Smoke middle', 'layouts/smoke-middle.php');
    $innerId = $createLayout($middleId, 'smoke-inner', 'Smoke inner', 'layouts/smoke-inner.php');

    $hierarchy = $resolver->resolve($innerId);
    $ids = array_map(static fn ($layout): int => $layout->id, $hierarchy);

    if ($ids !== [$outerId, $middleId, $innerId]) {
        throw new RuntimeException('Layout hierarchy order is invalid.');
    }

    if ($hierarchy[0]->parentId !== null
        || $hierarchy[1]->parentId !== $outerId
        || $hierarchy[2]->parentId !== $middleId) {
        throw new RuntimeException('Layout hierarchy parent metadata is invalid.');
    }

    $cycle = $db->prepare('UPDATE layouts SET parent_id = :parent_id WHERE id = :id');
    $cycle->execute([
        'parent_id' => $innerId,
        'id' => $outerId,
    ]);

    try {
        $resolver->resolve($innerId);
        throw new RuntimeException('Circular layout hierarchy was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Circular layout hierarchy was accepted.') {
            throw $exception;
        }
        if (!str_contains($exception->getMessage(), 'Circular layout hierarchy')) {
            throw $exception;
        }
    }

    $cycle->execute([
        'parent_id' => null,
        'id' => $outerId,
    ]);

    $parentId = null;
    for ($depth = 1; $depth <= LayoutHierarchyResolver::MAX_LAYOUT_STAGES + 1; ++$depth) {
        $parentId = $createLayout(
            $parentId,
            'smoke-depth-' . $depth,
            'Depth ' . $depth,
            'layouts/smoke-depth-' . $depth . '.php',
        );
    }

    try {
        $resolver->resolve((int) $parentId);
        throw new RuntimeException('Excessive layout hierarchy depth was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Excessive layout hierarchy depth was accepted.') {
            throw $exception;
        }
        if (!str_contains($exception->getMessage(), 'maximum depth')) {
            throw $exception;
        }
    }

    try {
        $resolver->resolve(PHP_INT_MAX);
        throw new RuntimeException('Missing layout hierarchy root was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Missing layout hierarchy root was accepted.') {
            throw $exception;
        }
        if (!str_contains($exception->getMessage(), 'was not found')) {
            throw $exception;
        }
    }
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

fwrite(STDOUT, "LAYOUT HIERARCHY OK\n");
