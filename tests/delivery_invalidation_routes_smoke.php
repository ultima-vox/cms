<?php

declare(strict_types=1);

use Core\Database;
use Core\Delivery\Cache\FilesystemCacheStore;
use Core\Delivery\Cache\FilesystemTagIndex;
use Core\Delivery\Cache\TaggedCache;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Http\Request;
use Core\Page\PageExecutionContext;
use Core\Repository\NodeModuleBindingRepository;
use Core\Security\Csrf;
use Core\Site\SiteContext;
use Core\View\Render\RenderContext;
use UltimaVox\Modules\Documents\DocumentRenderer;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
session_start(['use_cookies' => false, 'cache_limiter' => '']);

$db = Database::connection();
$rootPath = dirname(__DIR__);
$cacheRoot = sys_get_temp_dir() . '/uv-delivery-routes-' . bin2hex(random_bytes(6));
$siteIds = [];
$userId = null;
$core = null;
$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path) || is_link($path)) {
        if (file_exists($path) || is_link($path)) unlink($path);
        return;
    }
    foreach (new FilesystemIterator($path) as $entry) {
        $removeTree($entry->getPathname());
    }
    rmdir($path);
};

try {
    $insertSite = $db->prepare('INSERT INTO sites (code, name) VALUES (:code, :name) RETURNING id');
    foreach (['delivery-route-one', 'delivery-route-two'] as $code) {
        $insertSite->execute(['code' => $code, 'name' => $code]);
        $siteIds[] = (int) $insertSite->fetchColumn();
    }
    [$siteId, $otherSiteId] = $siteIds;
    $site = new SiteContext($siteId, 'delivery-route-one', 'Delivery routes', 'delivery-routes.test');
    $core = new ExtensionCore(new RuntimeApi(
        $db,
        $rootPath,
        $site,
        new TaggedCache(new FilesystemCacheStore($cacheRoot . '/data'), new FilesystemTagIndex($cacheRoot . '/index')),
    ));
    (new ModuleLoader($rootPath))->load($core);
    $core->freeze();

    $insertUser = $db->prepare(
        "INSERT INTO users (email, password_hash, display_name) VALUES (:email, :hash, 'Delivery fixture') RETURNING id"
    );
    $insertUser->execute([
        'email' => 'delivery-' . $siteId . '@fixture.invalid',
        'hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);
    $userId = (int) $insertUser->fetchColumn();
    $grantRole = $db->prepare("INSERT INTO user_roles (user_id, role_id) SELECT :id, id FROM roles WHERE code = 'editor'");
    $grantRole->execute(['id' => $userId]);
    $_SESSION['auth_user_id'] = $userId;
    $csrf = Csrf::token();
    $post = static fn (string $path, array $data): Request => new Request('POST', $path, $path, [], $data, [], []);

    // Real published Documents output supplies the dependencies used by a cached fragment/static page.
    $documents = new DocumentRepository($db);
    $renderer = new DocumentRenderer($documents);
    $documentId = $documents->create($siteId, 'delivery-main', 'Delivery main');
    $versionId = $documents->createDraftVersion($documentId, '<p>Before publication</p>');
    $documents->publishVersion($documentId, $versionId);
    $document = $documents->findActiveByCode($siteId, 'delivery-main');
    $context = new RenderContext();
    $context->dependency('site:' . $siteId);
    $html = (string) $renderer->render($document, $context);
    $core->cache()->put('document-output', $html, $context->dependencies(), 60);
    $docStatic = $core->delivery()->publishStatic($siteId, '/document', $html, $context->dependencies());
    $core->cache()->put('unrelated-document', 'Unchanged', ['site:' . $siteId, 'site:' . $siteId . ':document:unrelated'], 60);
    $core->cache()->put('other-site', 'Other site', ['site:' . $otherSiteId], 60);
    $otherStatic = $core->delivery()->publishStatic($otherSiteId, '/document', 'Other site', ['site:' . $otherSiteId]);

    $updateDocument = $core->routes()->handler('documents.update');
    $documentPath = '/admin/documents/' . $documentId;
    $data = ['name' => 'Delivery main', 'content' => '<p>After publication</p>', 'save_mode' => 'publish'];
    $response = $updateDocument($post($documentPath, $data + ['_csrf' => 'invalid']), ['id' => (string) $documentId]);
    if ($response->statusCode() !== 419 || $core->cache()->get('document-output') !== $html || !is_file($docStatic)) {
        throw new RuntimeException('Rejected Documents mutation evicted valid cached output.');
    }
    $response = $updateDocument($post($documentPath, $data + ['_csrf' => $csrf]), ['id' => (string) $documentId]);
    if ($response->statusCode() !== 302 || $core->cache()->get('document-output') !== null || is_file($docStatic)
        || $core->cache()->get('unrelated-document') !== 'Unchanged'
        || $core->cache()->get('other-site') !== 'Other site' || !is_file($otherStatic)
        || (string) $renderer->render($document, new RenderContext()) !== '<p>After publication</p>') {
        throw new RuntimeException('Documents publication did not invalidate only its dependent output.');
    }

    $insertSystem = $db->prepare(
        "INSERT INTO infosystems (site_id, name, code) VALUES (:site_id, :name, :code) RETURNING id"
    );
    $insertSystem->execute(['site_id' => $siteId, 'name' => 'Old system', 'code' => 'delivery-old']);
    $oldSystemId = (int) $insertSystem->fetchColumn();
    $insertSystem->execute(['site_id' => $siteId, 'name' => 'New system', 'code' => 'delivery-new']);
    $newSystemId = (int) $insertSystem->fetchColumn();
    $insertNode = $db->prepare(
        "INSERT INTO nodes (site_id, name, slug, path, page_type, status)
         VALUES (:site_id, 'Delivery node', '', '/', 'infosystem.list', 'published') RETURNING id"
    );
    $insertNode->execute(['site_id' => $siteId]);
    $nodeId = (int) $insertNode->fetchColumn();
    $bindings = new NodeModuleBindingRepository($db);
    $bindings->replaceTargetNodes($siteId, 'infosystem', 'primary', 'delivery-old', [$nodeId]);
    $context = new RenderContext();
    $context->dependency('site:' . $siteId);
    $context->dependency('site:' . $siteId . ':node:' . $nodeId);
    $node = ['id' => $nodeId, 'site_id' => $siteId, 'path' => '/', 'title' => 'Delivery node'];
    $pageContext = new PageExecutionContext(new Request('GET', '/', '/', [], [], [], []), $site, $node, [], $context);
    $html = (string) $core->pages()->resolve('infosystem.list')->execute($pageContext);
    if (!in_array('site:' . $siteId . ':infosystem:' . $oldSystemId, $context->dependencies(), true)) {
        throw new RuntimeException('Old Infosystem dependencies were not collected from real rendering.');
    }
    $core->cache()->put('old-system-page', $html, $context->dependencies(), 60);
    $systemStatic = $core->delivery()->publishStatic($siteId, '/system', $html, $context->dependencies());
    $updateBinding = $core->routes()->handler('infosystem.bindings.update');
    $bindingPath = '/admin/infosystems/' . $newSystemId . '/bindings';
    $bindingData = ['_csrf' => $csrf, 'node_ids' => [(string) $nodeId]];

    unset($_SESSION['auth_user_id']);
    $response = $updateBinding($post($bindingPath, $bindingData), ['id' => (string) $newSystemId]);
    if ($response->statusCode() !== 302 || $core->cache()->get('old-system-page') === null || !is_file($systemStatic)) {
        throw new RuntimeException('Unauthenticated redirect invalidated delivery artifacts.');
    }
    $_SESSION['auth_user_id'] = $userId;
    $response = $updateBinding($post($bindingPath, $bindingData), ['id' => (string) $newSystemId]);
    if ($response->statusCode() !== 302
        || $bindings->findTargetKey($nodeId, 'infosystem', 'primary') !== 'delivery-new'
        || $core->cache()->get('old-system-page') !== null || is_file($systemStatic)
        || $core->cache()->get('other-site') !== 'Other site' || !is_file($otherStatic)) {
        throw new RuntimeException('Infosystem rebinding left old-source output stale or evicted another site.');
    }
} finally {
    foreach ($siteIds as $id) {
        if ($core !== null) $core->delivery()->invalidate(['site:' . $id]);
        $delete = $db->prepare('DELETE FROM sites WHERE id = :id');
        $delete->execute(['id' => $id]);
    }
    if ($userId !== null) {
        $delete = $db->prepare('DELETE FROM audit_log WHERE user_id = :id');
        $delete->execute(['id' => $userId]);
        $delete = $db->prepare('DELETE FROM users WHERE id = :id');
        $delete->execute(['id' => $userId]);
    }
    $removeTree($cacheRoot);
    session_destroy();
}

fwrite(STDOUT, "DELIVERY INVALIDATION ROUTES OK\n");
