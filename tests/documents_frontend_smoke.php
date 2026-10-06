<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Http\Request;
use Core\Page\PageExecutionChain;
use Core\Page\PageExecutionContext;
use Core\Page\PageExecutorInterface;
use Core\Page\PageExecutorStage;
use Core\Page\PageRuntime;
use Core\Page\PageTypeProvisioningContext;
use Core\Site\SiteContext;
use Core\View\Render\RenderContext;
use Core\View\Render\RenderEngine;
use Core\View\Render\TemplateFacadeContext;
use Core\View\Render\ViewTemplateRenderer;
use Core\View\SafeHtml;
use UltimaVox\Modules\Documents\DocumentsFacade;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

$rootPath = dirname(__DIR__);
require $rootPath . '/vendor/autoload.php';

$db = Database::connection();
$core = new ExtensionCore(new RuntimeApi($db, $rootPath));
$loaded = (new ModuleLoader($rootPath))->load($core);

if (!in_array('documents', $loaded, true)) {
    throw new RuntimeException('Documents module was not discovered.');
}
if (!$core->pages()->has('documents.page')) {
    throw new RuntimeException('Documents page executor was not registered.');
}

$core->freeze();
$repository = new DocumentRepository($db);
$db->beginTransaction();

try {
    $documentId = $repository->create(1, 'footer-contacts', 'Footer contacts');
    $versionId = $repository->createDraftVersion($documentId, '<p>Primary footer</p>');
    $repository->publishVersion($documentId, $versionId);

    $autoProvisioned = $core->pages()->provisionConfiguration(
        'documents.page',
        new PageTypeProvisioningContext(
            siteId: 1,
            nodeId: 987654,
            nodeName: 'Provisioned page',
            nodeTitle: 'Provisioned title',
            nodePath: '/provisioned-page/',
        ),
    );
    if ($autoProvisioned !== ['document' => 'node-987654']) {
        throw new RuntimeException('Documents provisioner returned unexpected automatic configuration.');
    }

    $autoDocument = $repository->findActiveByCode(1, 'node-987654');
    if (!is_array($autoDocument) || ($autoDocument['name'] ?? null) !== 'Provisioned title') {
        throw new RuntimeException('Documents provisioner did not create the automatic document.');
    }
    $autoVersion = $repository->currentPublishedVersion((int) $autoDocument['id']);
    if (!is_array($autoVersion) || ($autoVersion['content'] ?? null) !== '') {
        throw new RuntimeException('Documents provisioner did not publish the initial empty document version.');
    }

    $autoProvisionedAgain = $core->pages()->provisionConfiguration(
        'documents.page',
        new PageTypeProvisioningContext(
            siteId: 1,
            nodeId: 987654,
            nodeName: 'Provisioned page',
            nodeTitle: 'Provisioned title',
            nodePath: '/provisioned-page/',
        ),
    );
    if ($autoProvisionedAgain !== $autoProvisioned) {
        throw new RuntimeException('Documents provisioner is not idempotent for an existing automatic document.');
    }

    $selectedProvisioned = $core->pages()->provisionConfiguration(
        'documents.page',
        new PageTypeProvisioningContext(
            siteId: 1,
            nodeId: 987655,
            nodeName: 'Selected document page',
            nodeTitle: 'Selected document page',
            nodePath: '/selected-document/',
            requestedConfiguration: ['document' => 'footer-contacts'],
        ),
    );
    if ($selectedProvisioned !== ['document' => 'footer-contacts']) {
        throw new RuntimeException('Documents provisioner did not preserve an explicitly selected document.');
    }

    $secondSite = $db->query(
        "INSERT INTO sites (code, name) VALUES ('documents-smoke', 'Documents Smoke') RETURNING id"
    );
    $secondSiteId = (int) $secondSite->fetchColumn();
    if ($secondSiteId < 1) {
        throw new RuntimeException('Documents smoke-test site was not created.');
    }

    $secondDocumentId = $repository->create($secondSiteId, 'footer-contacts', 'Second footer');
    $secondVersionId = $repository->createDraftVersion($secondDocumentId, '<p>Second footer</p>');
    $repository->publishVersion($secondDocumentId, $secondVersionId);

    $introDocumentId = $repository->create($secondSiteId, 'intro-doc', 'Intro document');
    $introVersionId = $repository->createDraftVersion($introDocumentId, '<section>Intro</section>');
    $repository->publishVersion($introDocumentId, $introVersionId);

    $defaultOnlyDocumentId = $repository->create(1, 'default-only-intro', 'Default-only intro');
    $defaultOnlyVersionId = $repository->createDraftVersion($defaultOnlyDocumentId, '<section>Wrong site</section>');
    $repository->publishVersion($defaultOnlyDocumentId, $defaultOnlyVersionId);

    $nodeStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO nodes (
            site_id, parent_id, name, slug, path, title, status, page_type, page_config
        )
        VALUES (
            :site_id, NULL, 'Documents Stage Root', '', '/', 'Documents Stage Root',
            'published', 'documents.page', '{"document":"footer-contacts"}'::jsonb
        )
        RETURNING id
        SQL
    );
    $nodeStatement->execute(['site_id' => $secondSiteId]);
    $stageNodeId = (int) $nodeStatement->fetchColumn();
    if ($stageNodeId < 1) {
        throw new RuntimeException('Documents intro stage fixture node was not created.');
    }

    $bindingStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO node_module_bindings (node_id, module_code, binding_code, target_key)
        VALUES (:node_id, 'documents', 'intro', :target_key)
        ON CONFLICT (node_id, module_code, binding_code)
        DO UPDATE SET
            target_key = EXCLUDED.target_key,
            updated_at = CURRENT_TIMESTAMP
        SQL
    );
    $bindingStatement->execute([
        'node_id' => $stageNodeId,
        'target_key' => 'intro-doc',
    ]);

    $stageContext = new PageExecutionContext(
        new Request('GET', '/', '/', [], [], [], []),
        new SiteContext($secondSiteId, 'documents-smoke', 'Documents Smoke', 'documents-smoke.test'),
        ['id' => $stageNodeId, 'site_id' => $secondSiteId, 'path' => '/'],
        [],
        new RenderContext(),
    );

    $providerStages = $core->pages()->stages($stageContext);
    if (count($providerStages) !== 1) {
        throw new RuntimeException('Documents intro stage provider returned an unexpected stage count.');
    }

    $terminal = new class implements PageExecutorInterface {
        public function execute(PageExecutionContext $context): SafeHtml
        {
            $context->renderContext->dependency('terminal:documents-intro-smoke');

            return SafeHtml::fromTrustedStorage('<main>Body</main>');
        }
    };
    $stageRuntime = new PageRuntime(
        $stageContext,
        new PageExecutionChain([
            ...$providerStages,
            new PageExecutorStage($terminal),
        ]),
    );
    if ($stageRuntime->start()->value() !== '<section>Intro</section><main>Body</main>') {
        throw new RuntimeException('Documents intro stage did not render before the terminal page executor.');
    }

    foreach ([
        'document:' . $introDocumentId,
        'site:' . $secondSiteId . ':document:' . $introDocumentId,
        'site:' . $secondSiteId . ':document:intro-doc',
        'document_version:' . $introVersionId,
        'terminal:documents-intro-smoke',
    ] as $dependency) {
        if (!in_array($dependency, $stageContext->renderContext->dependencies(), true)) {
            throw new RuntimeException('Documents intro stage dependency is missing: ' . $dependency);
        }
    }

    $withoutBinding = $core->pages()->stages(new PageExecutionContext(
        new Request('GET', '/unbound', '/unbound', [], [], [], []),
        new SiteContext($secondSiteId, 'documents-smoke', 'Documents Smoke', 'documents-smoke.test'),
        ['id' => 987654321, 'site_id' => $secondSiteId],
        [],
        new RenderContext(),
    ));
    if ($withoutBinding !== []) {
        throw new RuntimeException('Documents intro stage provider returned a stage without a binding.');
    }

    $bindingStatement->execute([
        'node_id' => $stageNodeId,
        'target_key' => 'default-only-intro',
    ]);
    try {
        $core->pages()->stages(new PageExecutionContext(
            new Request('GET', '/', '/', [], [], [], []),
            new SiteContext($secondSiteId, 'documents-smoke', 'Documents Smoke', 'documents-smoke.test'),
            ['id' => $stageNodeId, 'site_id' => $secondSiteId],
            [],
            new RenderContext(),
        ));
        throw new RuntimeException('Documents intro stage accepted a document from another site.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Documents intro stage accepted a document from another site.') {
            throw $exception;
        }
    }

    $bindingStatement->execute([
        'node_id' => $stageNodeId,
        'target_key' => 'intro-doc',
    ]);

    $engine = new RenderEngine();
    $templateContext = new TemplateFacadeContext(
        $engine,
        [
            'site' => new SiteContext(1, 'default', 'Default site', '127.0.0.1'),
            'node' => ['id' => 1, 'site_id' => 1],
        ],
        new ViewTemplateRenderer($rootPath, $core->templates()),
    );
    $facades = $core->templates()->instantiateFacades($templateContext);
    $documents = $facades['documents'] ?? null;
    if (!$documents instanceof DocumentsFacade) {
        throw new RuntimeException('Documents template facade was not exposed.');
    }

    $document = $documents->get('FOOTER-CONTACTS');
    if ($document->id() !== $documentId
        || $document->code() !== 'footer-contacts'
        || $document->name() !== 'Footer contacts') {
        throw new RuntimeException('Documents facade resolved an unexpected document.');
    }

    $html = (string) $document->execute();
    if ($html !== '<p>Primary footer</p>') {
        throw new RuntimeException('Executable document facade rendered unexpected content.');
    }

    $dependencies = $engine->context()->dependencies();
    foreach ([
        'document:' . $documentId,
        'site:1:document:' . $documentId,
        'site:1:document:footer-contacts',
        'document_version:' . $versionId,
    ] as $dependency) {
        if (!in_array($dependency, $dependencies, true)) {
            throw new RuntimeException('Document dependency tag is missing: ' . $dependency);
        }
    }

    $pageContext = new RenderContext();
    $executor = $core->pages()->resolve('documents.page');
    $pageHtml = $executor->execute(new PageExecutionContext(
        new Request('GET', '/document', '/document', [], [], [], []),
        new SiteContext($secondSiteId, 'documents-smoke', 'Documents Smoke', 'documents-smoke.test'),
        ['id' => 2, 'site_id' => $secondSiteId],
        ['document' => 'footer-contacts'],
        $pageContext,
    ));
    if ((string) $pageHtml !== '<p>Second footer</p>') {
        throw new RuntimeException('Documents page executor did not preserve site isolation.');
    }
    if (!in_array('document:' . $secondDocumentId, $pageContext->dependencies(), true)
        || !in_array('document_version:' . $secondVersionId, $pageContext->dependencies(), true)) {
        throw new RuntimeException('Documents page executor did not register render dependencies.');
    }

    try {
        $executor->execute(new PageExecutionContext(
            new Request('GET', '/missing', '/missing', [], [], [], []),
            new SiteContext(1, 'default', 'Default site', '127.0.0.1'),
            ['id' => 3, 'site_id' => 1],
            [],
            new RenderContext(),
        ));
        throw new RuntimeException('Documents page executor accepted missing configuration.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Documents page executor accepted missing configuration.') {
            throw $exception;
        }
    }
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

fwrite(STDOUT, "DOCUMENTS FRONTEND OK\n");
