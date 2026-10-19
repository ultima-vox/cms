<?php

declare(strict_types=1);

use Core\Database;
use Core\Content\HtmlSanitizer;
use Core\Repository\LayoutRepository;
use UltimaVox\Modules\Infosystem\Admin\SiteStylesheetResolver;
use UltimaVox\Modules\Infosystem\Repository\InfosystemItemSearchRepository;

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/modules/infosystem/autoload.php';

$db = Database::connection();
$id = (int) $db->query("SELECT id FROM infosystems WHERE site_id = 1 AND code = 'catalog'")->fetchColumn();
if ($id < 1) {
    throw new RuntimeException('Catalog fixture must be loaded first.');
}

$search = new InfosystemItemSearchRepository($db);
$rootItems = $search->search($id, 1, 25, null, 0);
foreach ($rootItems['items'] as $item) {
    if ($item['group_id'] !== null) {
        throw new RuntimeException('Infosystem root unexpectedly contains a nested item.');
    }
}
$groups = $db->prepare('SELECT id FROM infosystem_groups WHERE infosystem_id = :id LIMIT 1');
$groups->execute(['id' => $id]);
$groupId = (int) $groups->fetchColumn();
if ($groupId < 1) {
    throw new RuntimeException('Infosystem group fixture is missing.');
}
$groupItems = $search->search($id, 1, 25, null, $groupId);
if ($groupItems['total'] < 1) {
    throw new RuntimeException('Expected nested Infosystem fixture items.');
}
foreach ($groupItems['items'] as $item) {
    if ((int) ($item['group_id'] ?? 0) !== $groupId) {
        throw new RuntimeException('Infosystem group lookup leaked an item from another group.');
    }
}

$styles = (new SiteStylesheetResolver($root, new LayoutRepository($db, 1)))->resolve();
if (count($styles) > 8) {
    throw new RuntimeException('Editor loaded too many stylesheets.');
}
foreach ($styles as $style) {
    if (!preg_match('#^/assets/[a-zA-Z0-9_./-]+\\.css$#D', $style)
        || str_contains($style, '..') || !is_file($root . '/public' . $style)) {
        throw new RuntimeException('Editor stylesheet is not public and local.');
    }
}

foreach ([
    'public/assets/infosystem-editor.js',
    'public/assets/infosystem-editor.css',
    'modules/infosystem/templates/admin/infosystems/item_form.php',
] as $path) {
    if (!is_file($root . '/' . $path)) {
        throw new RuntimeException('Infosystem editor asset missing: ' . $path);
    }
}

$js = file_get_contents($root . '/public/assets/infosystem-editor.js');
if (!is_string($js) || !str_contains($js, "sandbox', 'allow-same-origin'")
    || !str_contains($js, 'frameHtml(')
    || !str_contains($js, "['html', 'HTML']")) {
    throw new RuntimeException('Sandboxed rich-editor and source modes are missing.');
}
$view = file_get_contents($root . '/modules/infosystem/templates/admin/infosystems/item_form.php');
foreach (['main', 'description', 'seo', 'properties', 'publish'] as $tab) {
    if (!str_contains((string) $view, 'data-tab-panel="' . $tab . '"')) {
        throw new RuntimeException('Missing editing tab: ' . $tab);
    }
}
if (!str_contains((string) $view, 'data-rich-editor="content"')
    || !str_contains((string) $view, 'data-rich-editor="description"')) {
    throw new RuntimeException('No reusable rich-editor instances found.');
}

// The server remains authoritative: executing JS/PHP from an editor is never permitted.
$sanitized = (new HtmlSanitizer())->rich('<p>Safe text</p><script>alert(1)</script><strong>Strong</strong>');
if (str_contains($sanitized, '<script') || !str_contains($sanitized, 'Safe text')) {
    throw new RuntimeException('HTML sanitizer lost its XSS boundary.');
}

echo "INFOSYSTEM EDITOR AND GROUP BROWSER OK\n";
