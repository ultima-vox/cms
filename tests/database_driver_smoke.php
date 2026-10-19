<?php

declare(strict_types=1);

use Core\Content\FolderRepository;
use Core\Database;
use Core\Database\SqlDialect;
use Core\Repository\LayoutRepository;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\StructureRepository;
use UltimaVox\Modules\Documents\Repository\DocumentManagementRepository;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;
use UltimaVox\Modules\Infosystem\Repository\InfosystemItemSearchRepository;
use UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository;
use UltimaVox\Modules\Infosystem\Repository\InfosystemRepository;
use UltimaVox\Modules\Sites\SitesManagementRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
foreach (['documents', 'infosystem', 'sites'] as $module) require dirname(__DIR__) . '/modules/' . $module . '/autoload.php';
$db = Database::connection();
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$reject = static function (callable $callback) use ($db): void {
    $db->exec('SAVEPOINT driver_rejection');
    $failed = false;
    try { $callback(); } catch (Throwable) { $failed = true; }
    $db->exec('ROLLBACK TO SAVEPOINT driver_rejection');
    if (!$failed) throw new RuntimeException('Unsafe cross-site or invalid operation accepted.');
};
$db->beginTransaction();
try {
    $sites = new SitesManagementRepository($db);
    $site = $sites->create('Driver B', 'driver-b');
    $sites->update($site, 'Driver B updated', true, 'html');
    $assert($sites->find($site)['name'] === 'Driver B updated', 'Site settings update failed');
    $sites->addDomain($site, 'driver-b.test', true);
    $layouts = new LayoutRepository($db, $site);
    $main = $layouts->defaultSystemId();
    $assert($main !== null && count($layouts->ancestry($main, 31)) === 1, 'Site layout seeding/ancestry failed');
    $layout = $layouts->create('Nested', 'layouts/driver.php', null);
    $q = $db->prepare('UPDATE layouts SET parent_id=:parent WHERE id=:id');
    $q->execute(['parent' => $main, 'id' => $layout]);
    $assert(count($layouts->ancestry($layout, 31)) === 2, 'Layout hierarchy failed');
    $folders = new FolderRepository($db, $site, 'documents');
    $folder = $folders->save(null, 'Документы', null);
    $child = $folders->save(null, 'Дочерняя', $folder);
    $folders->save($child, 'Переименована', $folder);
    $reject(fn () => $folders->save($folder, 'Cycle', $child));
    $reject(fn () => $folders->save(null, 'документы', null));
    $foreign = (new FolderRepository($db, 1, 'documents'))->save(null, 'Foreign driver', null);
    $reject(fn () => $folders->save(null, 'Wrong parent', $foreign));
    $docs = new DocumentRepository($db);
    $document = $docs->create($site, 'driver-document', 'Документ');
    $version = $docs->createDraftVersion($document, '<p>Driver content</p>');
    $docs->publishVersion($document, $version);
    $draft = $docs->createDraftVersion($document, '<p>New draft</p>');
    $management = new DocumentManagementRepository($db, $site);
    $management->move($document, $child);
    $reject(fn () => $management->move($document, $foreign));
    $assert((int) $management->all()[0]['draft_version_id'] === $draft, 'Latest draft selection failed');
    $assert($docs->currentPublishedVersion($document)['content'] === '<p>Driver content</p>', 'Document publication failed');
    $structure = new StructureRepository($db, $site);
    $root = $structure->create(['parent_id' => null, 'layout_id' => $main, 'name' => 'Home', 'slug' => '', 'title' => 'Home',
        'meta_description' => null, 'status' => 'published', 'is_active' => true, 'sorting' => 0, 'publish_at' => null], 'documents.page', ['document' => 'driver-document']);
    $assert($structure->find($root)['path'] === '/', 'Page provisioning storage failed');
    $systems = new InfosystemManagementRepository($db, $site);
    $system = $systems->create('Driver IS', 'driver-system', null, [], true);
    $group = $systems->createGroup($system, null, 'Group', 'group', null);
    $systems->updateGroup($system, $group, null, 'Renamed', 'renamed', null, 0, true);
    $data = ['name' => 'Ёжик 100%_!', 'slug' => 'driver-item', 'description' => null, 'content' => '<p>Item</p>',
        'meta_description' => null, 'status' => 'published', 'is_active' => true, 'sorting' => 0,
        'properties' => ['number' => 12, 'kind' => 'bio', 'enabled' => true], 'publish_at' => null];
    $item = $systems->saveItem(null, $system, $group, $data);
    $systems->saveItem($item, $system, $group, $data);
    $search = new InfosystemItemSearchRepository($db);
    $assert($search->search($system, 1, 25, '100%_!', null, 'published', ['number' => 12, 'enabled' => true])['total'] === 1, 'Literal search/typed JSON filters failed');
    $assert($search->search($system, 1, 25, null, null, null, ['number' => '12'])['total'] === 0, 'JSON number/string types mixed');
    $assert(count((new InfosystemRepository($db))->findPublishedItems($site, $system, filters: ['kind' => 'bio'])) === 1, 'Public typed JSON filter failed');
    $assert((new InfosystemRepository($db))->findPublishedItems(1, $system) === [], 'Public item crossed site boundary');
    $attempts = new LoginAttemptRepository($db);
    for ($i = 0; $i < 5; $i++) $attempts->recordFailure('driver@example.test', '127.0.0.1');
    $assert($attempts->isBlocked('driver@example.test', '127.0.0.1'), 'Login throttle failed');
    $attempts->clear('driver@example.test', '127.0.0.1');
    $assert(!$attempts->isBlocked('driver@example.test', '127.0.0.1'), 'Login throttle clear failed');
    $assert(SqlDialect::tableExists($db, 'documents') && !SqlDialect::tableExists($db, 'driver_missing'), 'Schema discovery failed');
    echo "DATABASE DRIVER OK: sites, folders, layouts, structure, documents, IS groups/items, typed JSON, literal search, login throttle\n";
} finally { if ($db->inTransaction()) $db->rollBack(); }
