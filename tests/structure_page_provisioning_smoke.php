<?php

declare(strict_types=1);

use Core\Bootstrap\BuiltinExtensions;
use Core\Database;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Page\PageConfigurationValidatorInterface;
use Core\Page\PageExecutionContext;
use Core\Page\PageExecutorInterface;
use Core\Page\PageTypeDefinition;
use Core\Page\PageTypeProvisionerInterface;
use Core\Page\PageTypeProvisioningContext;
use Core\View\SafeHtml;
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
        site_id, parent_id, name, slug, path, title, status, page_type, page_config
    )
    VALUES (
        :site_id, NULL, 'Provisioning Root', '', '/', 'Provisioning Root', 'draft', 'fixture.root', '{}'::jsonb
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
$fixtureExecutor = new class implements PageExecutorInterface {
    public function execute(PageExecutionContext $context): SafeHtml
    {
        return SafeHtml::fromTrustedStorage('Fixture page');
    }
};
$core->pages()->type(new PageTypeDefinition(
    code: 'fixture.page',
    name: 'Fixture page',
    executor: $fixtureExecutor,
    provisioner: new class implements PageTypeProvisionerInterface {
        public function provision(PageTypeProvisioningContext $context): array
        {
            return ['fixture' => 'selected'];
        }
    },
));
$core->pages()->type(new PageTypeDefinition(
    code: 'fixture.failure',
    name: 'Fail after provisioning',
    executor: $fixtureExecutor,
    configurationValidator: new class implements PageConfigurationValidatorInterface {
        public function validate(array $configuration): array
        {
            throw new RuntimeException('Fixture configuration rejected after resource creation.');
        }
    },
    provisioner: new class($db) implements PageTypeProvisionerInterface {
        public function __construct(private readonly PDO $db)
        {
        }

        public function provision(PageTypeProvisioningContext $context): array
        {
            (new DocumentRepository($this->db))->create(
                $context->siteId,
                'failed-change-' . $context->nodeId,
                'Resource that must roll back',
            );
            return [];
        }
    },
));
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

    $assertRejected = static function (callable $operation, string $message): void {
        try {
            $operation();
        } catch (RuntimeException $exception) {
            if (!str_contains($exception->getMessage(), $message)) {
                throw $exception;
            }
            return;
        }
        throw new RuntimeException('Expected operation rejection: ' . $message);
    };

    $nodeId = $service->create($nodeData('lifecycle-parent', 'Lifecycle parent'));
    $original = $structure->find($nodeId);
    $originalConfig = json_decode($original['page_config'], true, 512, JSON_THROW_ON_ERROR);
    $document = $documents->findActiveByCode($siteId, $originalConfig['document']);
    $documentId = (int) $document['id'];
    $content = '<p>Preserve this published content</p>';
    $versionId = $documents->createDraftVersion($documentId, $content);
    $documents->publishVersion($documentId, $versionId);

    $binding = $db->prepare(
        "INSERT INTO node_module_bindings (node_id, module_code, binding_code, target_key)
         VALUES (:node_id, 'documents', 'intro', :target_key)"
    );
    $binding->execute(['node_id' => $nodeId, 'target_key' => $originalConfig['document']]);

    $service->changeType($nodeId, 'documents.page', 'documents.page', ['document' => 'missing-document']);
    $structure->update($nodeId, array_merge($nodeData('lifecycle-parent', 'Renamed node'), [
        'page_type' => 'fixture.page',
        'page_config' => ['unexpected' => true],
    ]));
    $unchanged = $structure->find($nodeId);
    if ($unchanged['page_type'] !== 'documents.page' || $unchanged['page_config'] !== $original['page_config']) {
        throw new RuntimeException('Metadata update or same-type change overwrote page ownership/configuration.');
    }

    $assertRejected(
        fn () => $service->changeType($nodeId, 'infosystem.list', 'documents.page'),
        'не поддерживает автоматическую настройку',
    );
    $assertRejected(
        fn () => $service->changeType($nodeId, 'missing.type', 'documents.page'),
        'Unknown page type',
    );
    $foreignService = new StructurePageProvisioningService(
        $db, new StructureRepository($db, 1), new PageSelectionRepository($db), $core->pages(),
    );
    $assertRejected(
        fn () => $foreignService->changeType($nodeId, 'fixture.page', 'documents.page'),
        'текущего сайта',
    );

    $db->beginTransaction();
    try {
        $service->changeType($nodeId, 'fixture.page', 'documents.page');
        if (!$db->inTransaction() || $structure->find($nodeId)['page_type'] !== 'fixture.page') {
            throw new RuntimeException('Successful type change committed the caller transaction.');
        }
    } finally {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
    if ($structure->find($nodeId)['page_type'] !== 'documents.page') {
        throw new RuntimeException('Caller rollback did not restore page selection.');
    }

    $service->changeType($nodeId, 'fixture.page', 'documents.page');
    if ($structure->find($nodeId)['page_type'] !== 'fixture.page'
        || $documents->currentPublishedVersion($documentId)['content'] !== $content) {
        throw new RuntimeException('Page type change removed the previous module resource.');
    }
    $bindingCount = $db->prepare('SELECT COUNT(*) FROM node_module_bindings WHERE node_id = :id');
    $bindingCount->execute(['id' => $nodeId]);
    if ((int) $bindingCount->fetchColumn() !== 1) {
        throw new RuntimeException('Page type change removed module bindings.');
    }
    $assertRejected(
        fn () => $service->changeType($nodeId, 'documents.page', 'documents.page'),
        'уже изменён',
    );

    $db->beginTransaction();
    try {
        $db->exec('UPDATE nodes SET sorting = 17 WHERE id = ' . $nodeId);
        $assertRejected(
            fn () => $service->changeType($nodeId, 'fixture.failure', 'fixture.page'),
            'Fixture configuration rejected',
        );
        $afterFailure = $structure->find($nodeId);
        if (!$db->inTransaction() || (int) $afterFailure['sorting'] !== 17
            || $afterFailure['page_type'] !== 'fixture.page'
            || $documents->findActiveByCode($siteId, 'failed-change-' . $nodeId) !== null) {
            throw new RuntimeException('Failed page type change did not isolate rollback from caller transaction.');
        }
    } finally {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
    $assertRejected(
        fn () => $service->changeType($nodeId, 'fixture.failure', 'fixture.page'),
        'Fixture configuration rejected',
    );
    if ($db->inTransaction() || $documents->findActiveByCode($siteId, 'failed-change-' . $nodeId) !== null) {
        throw new RuntimeException('Failed page type change left a transaction or orphan resource.');
    }

    $service->changeType($nodeId, 'documents.page', 'fixture.page');
    $restored = $structure->find($nodeId);
    if ($restored['page_config'] !== $original['page_config']
        || $documents->currentPublishedVersion($documentId)['content'] !== $content) {
        throw new RuntimeException('Returning to Documents did not reuse the existing node document.');
    }

    $childData = $nodeData('lifecycle-child', 'Lifecycle child');
    $childData['parent_id'] = $nodeId;
    $childId = $service->create($childData, 'documents.page', $originalConfig);
    $binding->execute(['node_id' => $childId, 'target_key' => $originalConfig['document']]);
    $sharedId = $service->create($nodeData('shared-document', 'Shared document'), 'documents.page', $originalConfig);
    $assertRejected(fn () => $structure->delete($rootNodeId), 'корневым');
    $foreignStructure = new StructureRepository($db, 1);
    $assertRejected(fn () => $foreignStructure->delete($nodeId), 'не найден');
    $structure->delete($nodeId);
    $bindingCount->execute(['id' => $childId]);
    if ($structure->find($nodeId) !== null || $structure->find($childId) !== null
        || (int) $bindingCount->fetchColumn() !== 0
        || $structure->find($sharedId) === null
        || $documents->currentPublishedVersion($documentId)['content'] !== $content) {
        throw new RuntimeException('Subtree deletion did not detach bindings while preserving shared Documents.');
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
