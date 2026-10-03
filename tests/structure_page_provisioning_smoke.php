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

$rootStatement = $db->query(
    'SELECT id FROM nodes WHERE site_id = 1 AND parent_id IS NULL ORDER BY id LIMIT 1'
);
$rootNodeId = (int) $rootStatement->fetchColumn();
if ($rootNodeId < 1) {
    throw new RuntimeException('Structure provisioning smoke requires the default site root node.');
}

$core = new ExtensionCore(new RuntimeApi($db, $rootPath));
(new BuiltinExtensions())->register($core);
(new ModuleLoader($rootPath))->load($core);
$core->freeze();

$structure = new StructureRepository($db, 1);
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

    $document = $documents->findActiveByCode(1, $expectedCode);
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
    'SELECT COUNT(*) FROM nodes WHERE site_id = 1 AND slug = :slug'
);
$failedNode->execute(['slug' => $failedSlug]);
if ((int) $failedNode->fetchColumn() !== 0) {
    throw new RuntimeException('Failed page provisioning did not roll back the Structure node.');
}

fwrite(STDOUT, "STRUCTURE PAGE PROVISIONING OK\n");
