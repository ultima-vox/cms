<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Http\Request;
use Core\Page\PageExecutionContext;
use Core\Site\SiteContext;
use Core\View\Render\RenderContext;
use Core\View\Render\RenderEngine;
use Core\View\Render\TemplateFacadeContext;
use Core\View\Render\ViewTemplateRenderer;
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
