<?php

declare(strict_types=1);
$root = $argv[1];
if (getenv('DB_NAME') !== 'cms_upgrade_runtime') throw new RuntimeException('Disposable upgrade database required.');
require $root . '/vendor/autoload.php';
require $root . '/modules/documents/autoload.php';
$db = Core\Database::connection();
$users = $db->prepare('INSERT INTO users(email, password_hash, display_name) VALUES(:email, :hash, :name) RETURNING id');
$users->execute(['email' => 'upgrade@example.test', 'hash' => password_hash(getenv('CMS_ADMIN_PASSWORD'), PASSWORD_DEFAULT), 'name' => 'Upgrade operator']);
$id = (int) $users->fetchColumn();
$db->exec("INSERT INTO user_roles(user_id, role_id) SELECT $id, id FROM roles WHERE code = 'superadmin'");
$db->exec("INSERT INTO sites(code, name) VALUES('upgrade-second', 'Existing second site')");
$site = (int) $db->query("SELECT id FROM sites WHERE code = 'upgrade-second'")->fetchColumn();
$db->exec("INSERT INTO layouts(code, name, template_path, parent_id) SELECT 'upgrade-custom', 'Shared legacy layout', 'layouts/upgrade-custom.php', id FROM layouts WHERE code = 'main'");
$layout = (int) $db->query("SELECT id FROM layouts WHERE code = 'upgrade-custom'")->fetchColumn();
$documents = new UltimaVox\Modules\Documents\Repository\DocumentRepository($db);
foreach ([1, $site] as $siteId) {
    $document = $documents->create($siteId, 'upgrade-content', 'Existing content ' . $siteId);
    $version = $documents->createDraftVersion($document, '<p>Preserved published content ' . $siteId . '</p>');
    $documents->publishVersion($document, $version);
    $q = $db->prepare("INSERT INTO nodes(site_id, name, slug, path, title, layout_id, status, page_type, page_config) VALUES(:site, 'Existing page', '', '/', 'Existing title', :layout, 'published', 'documents.page', '{\"document\":\"upgrade-content\"}')");
    $q->execute(['site' => $siteId, 'layout' => $layout]);
}
if (!is_dir($root . '/storage/templates/layouts')) mkdir($root . '/storage/templates/layouts', 0755, true);
file_put_contents($root . '/storage/templates/layouts/upgrade-custom.php', '<?php echo "LEGACY-LAYOUT"; echo $page->execute(); ?>');
echo "Populated baseline prepared: two sites share a custom layout, two published documents\n";
