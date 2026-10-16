<?php

declare(strict_types=1);

use Core\Application;
use Core\Bootstrap\BuiltinExtensions;
use Core\Controller\NodeController;
use Core\Database;
use Core\Delivery\Page\PageCache;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Http\Request;
use Core\Layout\LayoutHierarchyResolver;
use Core\Page\PageExecutionContext;
use Core\Page\PageExecutionStageInterface;
use Core\Page\PageExecutionStageProviderInterface;
use Core\Page\PageRenderer;
use Core\Page\PageRuntime;
use Core\Repository\LayoutRepository;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\NodeRepository;
use Core\Repository\SiteRepository;
use Core\Repository\UserRepository;
use Core\Security\AuthService;
use Core\Site\SiteResolver;
use Core\View\PhpRenderer;
use Core\View\SafeHtml;
use UltimaVox\Modules\Sites\SitesManagementRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
require_once $root . '/modules/sites/autoload.php';
$db = Database::connection();
$application = new Application($root);
$siteManagement = new SitesManagementRepository($db);
$createdFiles = [];
$sites = [];
$assertRejected = static function (callable $operation): void {
    try {
        $operation();
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException('Static generation accepted an ineligible page.');
};
$db->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(5));
    foreach (['one', 'two'] as $label) {
        $code = 'static-' . $label . '-' . $suffix;
        $host = $code . '.test';
        $siteId = $siteManagement->create($label, $code, $label === 'two' ? 'html' : 'clean');
        $statement = $db->prepare('INSERT INTO site_domains (site_id, host, is_primary) VALUES (:site, :host, TRUE)');
        $statement->execute(['site' => $siteId, 'host' => $host]);
        $statement = $db->prepare('INSERT INTO documents (site_id, code, name) VALUES (:site, :code, :name) RETURNING id');
        $statement->execute(['site' => $siteId, 'code' => $code, 'name' => $label]);
        $documentId = (int) $statement->fetchColumn();
        $statement = $db->prepare(
            "INSERT INTO document_versions (document_id, version, content, status, published_at)
             VALUES (:document, 1, :content, 'published', CURRENT_TIMESTAMP)",
        );
        $statement->execute(['document' => $documentId, 'content' => '<p>Static fixture ' . $label . '</p>']);
        $statement = $db->prepare(
            "INSERT INTO nodes (site_id, name, slug, path, title, status, page_type, page_config)
             VALUES (:site, :name, '', '/', :title, 'published', 'documents.page', CAST(:config AS jsonb)) RETURNING id",
        );
        $statement->execute([
            'site' => $siteId, 'name' => $label, 'title' => $label,
            'config' => json_encode(['document' => $code], JSON_THROW_ON_ERROR),
        ]);
        $parentId = (int) $statement->fetchColumn();
        $statement = $db->prepare(
            "INSERT INTO nodes (site_id, parent_id, name, slug, path, title, status, page_type, page_config)
             VALUES (:site, :parent, :name, 'about', '/about', :title, 'published', 'documents.page', CAST(:config AS jsonb)) RETURNING id",
        );
        $statement->execute([
            'site' => $siteId, 'parent' => $parentId, 'name' => $label, 'title' => $label,
            'config' => json_encode(['document' => $code], JSON_THROW_ON_ERROR),
        ]);
        $sites[] = ['id' => $siteId, 'code' => $code, 'host' => $host, 'node' => (int) $statement->fetchColumn()];
    }

    $resolver = new SiteResolver(new SiteRepository($db), 'http://localhost');
    $requestFor = static fn (array $site): Request => new Request('GET', '/about', '/about', [], [], ['HTTP_HOST' => $site['host']], []);
    $site = $resolver->resolve($requestFor($sites[0]));
    if ($site === null) {
        throw new RuntimeException('Static fixture site did not resolve.');
    }
    $core = new ExtensionCore(new RuntimeApi($db, $root, $site));
    (new BuiltinExtensions())->register($core);
    (new ModuleLoader($root))->load($core);
    $provider = new class implements PageExecutionStageProviderInterface {
        public bool $block = false;

        public function stages(PageExecutionContext $context): array
        {
            if ($this->block) {
                $context->renderContext->doNotCache('smoke.personalized');
            }
            $context->renderContext->dependency('static-generation:stage');
            return [new class implements PageExecutionStageInterface {
                public function execute(PageRuntime $page): SafeHtml
                {
                    return SafeHtml::fromTrustedStorage('<aside>shared-stage</aside>' . $page->execute()->value());
                }
            }];
        }
    };
    $core->pages()->stageProvider('smoke.static-stage', $provider);
    $core->freeze();
    $php = new PhpRenderer($root, $core);
    $renderer = new PageRenderer(new NodeRepository($db), $php, $core->pages(), new LayoutHierarchyResolver(new LayoutRepository($db)));
    $controller = new NodeController(
        $resolver, $php, $renderer, new PageCache($core->cache()), $core->delivery(),
        new AuthService(new UserRepository($db), new LoginAttemptRepository($db)),
    );
    $request = $requestFor($sites[0]);
    $generation = $core->delivery()->beginStaticGeneration();
    $rendered = $renderer->render($request, $site);
    if ($rendered === null || !str_contains($rendered->html, 'shared-stage')) {
        throw new RuntimeException('Shared renderer skipped registered execution stages.');
    }
    foreach (['site:' . $site->id, 'site:' . $site->id . ':node:' . $sites[0]['node'], 'static-generation:stage'] as $tag) {
        if (!in_array($tag, $rendered->context->dependencies(), true)) {
            throw new RuntimeException('Shared renderer lost render dependencies.');
        }
    }
    $static = $core->delivery()->publishRenderedStatic($site->id, '/about', $rendered, $generation, 'html');
    $createdFiles[] = $static;
    ob_start();
    $controller->resolve($request)->send();
    $httpHtml = (string) ob_get_clean();
    if ($httpHtml !== file_get_contents($static)) {
        throw new RuntimeException('HTTP and static output used different page composition.');
    }
    $provider->block = true;
    $generation = $core->delivery()->beginStaticGeneration();
    $private = $renderer->render($request, $site);
    $assertRejected(fn () => $core->delivery()->publishRenderedStatic($site->id, '/about', $private, $generation, 'html'));
    if (is_file($static)) {
        throw new RuntimeException('A render veto left an older static snapshot behind.');
    }

    // Application boot uses real enabled modules, published Documents and the primary host.
    $first = $application->publishStatic($sites[0]['code'], '/about');
    $second = $application->publishStatic($sites[1]['code'], '/about');
    if (!str_ends_with($first, '/about/index.html') || !str_ends_with($second, '/about.html')) {
        throw new RuntimeException('Static generation ignored independent per-site defaults.');
    }
    $override = $application->publishStatic($sites[0]['code'], '/about', 'html');
    $createdFiles[] = $override;
    if (!str_ends_with($override, '/about.html')
        || $siteManagement->find($sites[0]['id'])['settings']['permalink_style'] !== 'clean') {
        throw new RuntimeException('Explicit publication override changed the persisted site default.');
    }
    $createdFiles = [...$createdFiles, $first, $second];
    if (!str_contains((string) file_get_contents($first), 'Static fixture one')
        || !str_contains((string) file_get_contents($second), 'Static fixture two')
        || str_contains((string) file_get_contents($first), 'Static fixture two')) {
        throw new RuntimeException('Static application boot lost module content or site isolation.');
    }
    $core->delivery()->invalidate(['site:' . $site->id . ':node:' . $sites[0]['node']]);
    if (is_file($first) || !is_file($second)) {
        throw new RuntimeException('Generated output did not retain selective render dependencies.');
    }
    $first = $application->publishStatic($sites[0]['code'], '/about', 'clean');
    $db->exec("UPDATE nodes SET status = 'draft' WHERE id = " . $sites[0]['node']);
    $assertRejected(fn () => $application->publishStatic($sites[0]['code'], '/about', 'clean'));
    if (is_file($first)) {
        throw new RuntimeException('Unpublished page retained its older output variant.');
    }
    $assertRejected(fn () => $siteManagement->update($sites[1]['id'], 'two', true, 'invalid'));
    $assertRejected(fn () => $siteManagement->create('Bad policy', 'bad-policy-' . $suffix, 'invalid'));
    $assertRejected(fn () => $application->publishStatic('unknown-' . $suffix, '/about'));
    $assertRejected(fn () => $application->publishStatic($sites[0]['code'], '/admin'));
    $assertRejected(fn () => $application->publishStatic($sites[0]['code'], '/../escape'));
    $db->exec('UPDATE sites SET is_active = FALSE WHERE id = ' . $sites[1]['id']);
    $assertRejected(fn () => $application->publishStatic($sites[1]['code'], '/about'));
} finally {
    foreach ($sites as $record) {
        if (isset($core)) {
            $core->delivery()->invalidate(['site:' . $record['id']]);
        }
    }
    foreach ($createdFiles as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    $db->rollBack();
}

fwrite(STDOUT, "STATIC PAGE GENERATION OK\n");
