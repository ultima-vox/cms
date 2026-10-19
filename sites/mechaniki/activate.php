#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Database;
use Core\Repository\LayoutRepository;
use Core\Repository\PageSelectionRepository;
use Core\Repository\SiteRepository;
use Core\Repository\StructureRepository;
use Dotenv\Dotenv;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
require_once $root . '/modules/documents/autoload.php';
Dotenv::createImmutable($root)->safeLoad();

$apply = in_array('--apply', $argv, true);
$refresh = in_array('--refresh', $argv, true);
if ($refresh && !$apply) {
    fwrite(STDERR, "--refresh requires --apply.\n");
    exit(1);
}
if (array_diff(array_slice($argv, 1), ['--apply', '--refresh']) !== []) {
    fwrite(STDERR, "Usage: php sites/mechaniki/activate.php [--apply [--refresh]]\n");
    exit(1);
}

$documentsRoot = __DIR__ . '/pages';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($documentsRoot, FilesystemIterator::SKIP_DOTS),
);
$pages = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'html') {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($documentsRoot) + 1);
    $name = str_replace(DIRECTORY_SEPARATOR, '/', substr($relative, 0, -5));
    $path = $name === 'home' ? '/' : '/' . $name;
    if (!preg_match('#^/(?:[a-z0-9-]+(?:/[a-z0-9-]+)*)?$#D', $path)) {
        throw new RuntimeException('Invalid packaged page path: ' . $path);
    }
    $html = file_get_contents($file->getPathname());
    if (!is_string($html)) {
        throw new RuntimeException('Unable to read packaged document.');
    }
    // Forms are presentation-only on staging: prevent keyboard/JS-off submission.
    $html = preg_replace('/<form\\b/i', '<form inert aria-disabled="true"', $html);
    if (!is_string($html) || $html === '' || str_contains($html, '<?')
        || preg_match('/<script\b/i', $html) === 1) {
        throw new RuntimeException('Page is empty or contains executable markup: ' . $path);
    }
    $heading = preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $match) === 1
        ? trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        : '';
    $title = mb_substr($heading !== '' ? $heading : ($path === '/' ? 'Автосервис Механики' : basename($name)), 0, 255);
    $code = 'mechaniki-' . ($path === '/' ? 'home' : str_replace('/', '-', ltrim($path, '/')));
    if (strlen($code) > 119) {
        $code = 'mechaniki-' . substr(hash('sha256', $path), 0, 24);
    }
    $pages[$path] = ['html' => $html, 'title' => $title, 'code' => $code];
}
uksort($pages, static fn (string $a, string $b): int => [substr_count($a, '/'), $a]
    <=> [substr_count($b, '/'), $b]);

$db = Database::connection();
$site = (new SiteRepository($db))->findActiveByHost('ru.rewired.ru');
if ($site === null) {
    throw new RuntimeException('ru.rewired.ru is not assigned to an active CMS site; activation refused.');
}
$siteId = (int) $site['id'];

$module = $db->prepare('SELECT is_enabled FROM installed_modules WHERE code = :code');
$module->execute(['code' => 'documents']);
$enabled = $module->fetchColumn();
if (!in_array($enabled, [true, 1, '1', 't', 'true'], true)) {
    throw new RuntimeException('Documents module must be enabled before importing pages.');
}

$structure = new StructureRepository($db, $siteId);
$existing = [];
foreach ($structure->all() as $node) {
    $existing[(string) $node['path']] = $node;
}
foreach ($pages as $path => $page) {
    if (isset($existing[$path]) && $path !== '/') {
        // StructureRepository::all() does not expose page_type, load the full node.
        $current = $structure->find((int) $existing[$path]['id']);
        if (($current['page_type'] ?? null) !== 'documents.page') {
            throw new RuntimeException('Non-Documents page already owns ' . $path . '; refused to overwrite.');
        }
    }
}

fwrite(STDOUT, sprintf(
    "Target: ru.rewired.ru, site #%d, packaged pages: %d, existing matching nodes: %d\n",
    $siteId,
    count($pages),
    count(array_intersect(array_keys($pages), array_keys($existing))),
));
if (!$apply) {
    fwrite(STDOUT, "Dry-run only. Use --apply to activate the packaged design.\n");
    exit(0);
}

$layouts = new LayoutRepository($db, $siteId);
$documents = new DocumentRepository($db);
$selection = new PageSelectionRepository($db);
$created = 0;
$updated = 0;

$db->beginTransaction();
try {
    $layout = $layouts->findByCode('mechaniki');
    $layoutId = $layout !== null
        ? (int) $layout['id']
        : $layouts->create('Механики — тестовый дизайн', 'layouts/mechaniki.php', 'Стенд ru.rewired.ru');
    $nodeIds = [];
    foreach ($pages as $path => $page) {
        $document = $documents->findActiveByCode($siteId, $page['code']);
        $documentId = $document !== null
            ? (int) $document['id']
            : $documents->create($siteId, $page['code'], $page['title']);
        $published = $documents->currentPublishedVersion($documentId);
        if ($published === null || ($refresh && (string) $published['content'] !== $page['html'])) {
            $versionId = $documents->createDraftVersion($documentId, $page['html']);
            $documents->publishVersion($documentId, $versionId);
            $updated++;
        }

        $node = $existing[$path] ?? null;
        if ($node === null) {
            $parentPath = dirname($path);
            $parentPath = $parentPath === '.' ? '/' : $parentPath;
            $parentId = $path === '/' ? null : ($nodeIds[$parentPath] ?? null);
            if ($path !== '/' && $parentId === null) {
                throw new RuntimeException('Missing Structure parent for ' . $path);
            }
            $id = $structure->create([
                'parent_id' => $parentId,
                'layout_id' => $layoutId,
                'name' => $page['title'],
                'slug' => $path === '/' ? '' : basename($path),
                'title' => $page['title'],
                'meta_description' => null,
                'status' => 'published',
                'is_active' => true,
                'sorting' => 0,
                'publish_at' => null,
            ], 'documents.page', ['document' => $page['code']]);
            $created++;
        } else {
            $id = (int) $node['id'];
            $update = $db->prepare(
                'UPDATE nodes SET layout_id = :layout_id, title = :title, status = :status,
                 is_active = TRUE, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND site_id = :site_id'
            );
            $update->execute([
                'layout_id' => $layoutId, 'title' => $page['title'],
                'status' => 'published', 'id' => $id, 'site_id' => $siteId,
            ]);
            $selection->assign($id, $siteId, 'documents.page', ['document' => $page['code']]);
        }
        $nodeIds[$path] = $id;
    }
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

fwrite(STDOUT, sprintf("Activated %d pages (%d new nodes, %d document versions).\n", count($pages), $created, $updated));
fwrite(STDOUT, "Verify the test host, asset URLs, CSS/JS and page cache before considering deployment complete.\n");
