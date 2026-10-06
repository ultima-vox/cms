<?php

declare(strict_types=1);

use Core\Bootstrap\BuiltinExtensions;
use Core\Database;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Repository\PageSelectionRepository;
use Core\Repository\StructureRepository;
use Core\Structure\StructurePageProvisioningService;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/modules/documents/autoload.php';

$rootPath = dirname(__DIR__);
$db = Database::connection();

$siteStatement = $db->query(
    "INSERT INTO sites (code, name) VALUES ('structure-provisioning-smoke', 'Structure Provisioning Smoke') RETURNING id"
);
$siteId = (int) $siteStatement->fetchColumn();
if ($siteId < 1) {
    throw new RuntimeException('Structure provisioning smoke site was not created.');
}

$rootStatement = $db->prepare(
    <<<'SQL'
    INSERT INTO nodes (
        site_id, parent_id, name, slug, path, title, content, status, page_type, page_config
    )
    VALUES (
        :site_id, NULL, 'Provisioning Root', '', '/', 'Provisioning Root', '', 'draft', 'fixture.root', '{}'::jsonb
    )
    RETURNING id
    SQL
);
$rootStatement->execute(['site_id' => $siteId]);
$rootNodeId = (int) $rootStatement->fetchColumn();
if ($rootNodeId < 1) {
    throw new RuntimeException('Structure provisioning smoke root node was not created.');
}

$core = new ExtensionCore(new RuntimeApi($db, $rootPath));
(new BuiltinExtensions())->register($core);
(new ModuleLoader($rootPath))->load($core);
$core->freeze();

$provisionableCodes = array_map(
    static fn ($definition): string => $definition->code,
    $core->pages()->provisionableDefinitions(),
);
if (!in_array('documents.page', $provisionableCodes, true)
    || in_array('core.content', $provisionableCodes, true)
    || in_array('infosystem.list', $provisionableCodes, true)) {
    throw new RuntimeException('Provisionable page type registry exposed an unsafe Structure choice.');
}

$structure = new StructureRepository($db, $siteId);
$service = new StructurePageProvisioningService(
    $db,
    $structure,
    new PageSelectionRepository($db),
    $core->pages(),
);
$documents = new DocumentRepository($db);

$nodeData = static fn (string $slug, string $name): array => [
    'parent_id' => $rootNodeId,
    'layout_id' => null,
    'name' => $name,
    'slug' => $slug,
    'title' => $name . ' title',
    'content' => '',
    'meta_description' => null,
    'status' => 'draft',
    'is_active' => false,
    'sorting' => 0,
    'publish_at' => null,
];

try {
    $db->beginTransaction();
    try {
        $nodeId = $service->create($nodeData('page-provisioning-success', 'Provisioned page'));
        $node = $structure->find($nodeId);
        if (!is_array($node) || ($node['page_type'] ?? null) !== 'documents.page') {
            throw new RuntimeException('Structure service did not select the registered default page type.');
        }

        $configuration = json_decode(
            (string) ($node['page_config'] ?? '{}'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $expectedCode = 'node-' . $nodeId;
        if (!is_array($configuration) || ($configuration['document'] ?? null) !== $expectedCode) {
            throw new RuntimeException('Structure service did not persist provisioned page configuration.');
        }

        $document = $documents->findActiveByCode($siteId, $expectedCode);
        if (!is_array($document) || ($document['name'] ?? null) !== 'Provisioned page title') {
            throw new RuntimeException('Default page provisioner did not create the module-owned resource.');
        }

        $published = $documents->currentPublishedVersion((int) $document['id']);
        if (!is_array($published) || ($published['status'] ?? null) !== 'published') {
            throw new RuntimeException('Default page provisioner did not publish an initial document version.');
        }
    } finally {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }

    $legacySlug = 'legacy-core-content-rejected';
    try {
        $service->create(
            $nodeData($legacySlug, 'Legacy page'),
            'core.content',
        );
        throw new RuntimeException('Structure provisioning accepted a page type without a provisioner.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Structure provisioning accepted a page type without a provisioner.') {
            throw $exception;
        }
    }

    $legacyNode = $db->prepare(
        'SELECT COUNT(*) FROM nodes WHERE site_id = :site_id AND slug = :slug'
    );
    $legacyNode->execute([
        'site_id' => $siteId,
        'slug' => $legacySlug,
    ]);
    if ((int) $legacyNode->fetchColumn() !== 0) {
        throw new RuntimeException('Rejected legacy page type still created a Structure node.');
    }

    $failedSlug = 'page-provisioning-rollback';
    try {
        $service->create(
            $nodeData($failedSlug, 'Rollback page'),
            'documents.page',
            ['document' => 'missing-provisioning-document'],
        );
        throw new RuntimeException('Structure provisioning accepted a missing explicitly selected document.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Structure provisioning accepted a missing explicitly selected document.') {
            throw $exception;
        }
    }

    $failedNode = $db->prepare(
        'SELECT COUNT(*) FROM nodes WHERE site_id = :site_id AND slug = :slug'
    );
    $failedNode->execute([
        'site_id' => $siteId,
        'slug' => $failedSlug,
    ]);
    if ((int) $failedNode->fetchColumn() !== 0) {
        throw new RuntimeException('Failed page provisioning did not roll back the Structure node.');
    }
} finally {
    $deleteNodes = $db->prepare('DELETE FROM nodes WHERE site_id = :site_id');
    $deleteNodes->execute(['site_id' => $siteId]);

    $deleteDocuments = $db->prepare('DELETE FROM documents WHERE site_id = :site_id');
    $deleteDocuments->execute(['site_id' => $siteId]);

    $deleteSite = $db->prepare('DELETE FROM sites WHERE id = :site_id');
    $deleteSite->execute(['site_id' => $siteId]);
}

fwrite(STDOUT, "STRUCTURE PAGE PROVISIONING OK\n");
