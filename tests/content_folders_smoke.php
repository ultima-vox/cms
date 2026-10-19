<?php

declare(strict_types=1);

use Core\Content\FolderRepository;
use Core\Database;
use Core\Repository\LayoutRepository;
use Core\Site\SiteAccessRepository;
use UltimaVox\Modules\Documents\Repository\DocumentManagementRepository;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;
use UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/modules/documents/autoload.php';
require dirname(__DIR__) . '/modules/infosystem/autoload.php';
$db = Database::connection();
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$reject = static function (callable $action) use ($db): void {
    $db->exec('SAVEPOINT folder_rejection');
    $failed = false;
    try { $action(); } catch (Throwable) { $failed = true; }
    $db->exec('ROLLBACK TO SAVEPOINT folder_rejection');
    if (!$failed) throw new RuntimeException('Unsafe folder operation accepted.');
};
$db->beginTransaction();
try {
    $siteB = (int) $db->query("INSERT INTO sites(code, name) VALUES('folders-unit-b', 'Folders B') RETURNING id")->fetchColumn();
    $a = new FolderRepository($db, 1, 'documents'); $b = new FolderRepository($db, $siteB, 'documents');
    $root = $a->save(null, 'Папка', null); $child = $a->save(null, 'Вложенная', $root);
    $other = $b->save(null, 'Папка', null);
    $isFolder = (new FolderRepository($db, 1, 'infosystems'))->save(null, 'Папка', null);
    $assert($a->find($other) === null && $b->find($root) === null, 'Folder crossed site boundary');
    $reject(fn () => $a->save(null, 'Foreign parent', $other));
    $reject(fn () => $a->save($root, 'Cycle', $child));
    $reject(fn () => $a->save(null, 'папка', null));
    $reject(fn () => $a->delete($root));
    $reject(fn () => $db->exec("UPDATE content_folders SET parent_id = $child WHERE id = $root"));
    $reject(fn () => $db->exec("UPDATE content_folders SET parent_id = $other WHERE id = $child"));
    $docs = new DocumentRepository($db);
    $document = $docs->create(1, 'folder-unit', 'Folder unit');
    $manage = new DocumentManagementRepository($db, 1); $manage->move($document, $child);
    $reject(fn () => $manage->move($document, $other));
    $reject(fn () => $manage->move($document, $isFolder));
    $reject(fn () => $db->exec("UPDATE documents SET folder_id = $other WHERE id = $document"));
    $reject(fn () => $db->exec("UPDATE documents SET folder_id = $isFolder WHERE id = $document"));
    $reject(fn () => $a->delete($child));
    $assert(count($a->filter($manage->all(), $a->browser($child, '/admin/documents'))) === 1, 'Folder filtering failed');
    $manage->move($document, $root); $a->delete($child);
    $assert($manage->find($document)['code'] === 'folder-unit', 'Move changed stable content code');
    $deep = $a->save(null, 'Depth root', null);
    for ($i = 0; $i < 62; $i++) $deep = $a->save(null, 'Level ' . $i, $deep);
    $leaf = $a->save(null, 'Depth 64', $deep);
    $reject(fn () => $a->save(null, 'Depth 65', $leaf));
    $branch = $a->save(null, 'Movable branch', null);
    $a->save(null, 'Branch child', $branch);
    $reject(fn () => $a->save($branch, 'Too deep subtree', $deep));
    $systems = new InfosystemManagementRepository($db, 1);
    $system = $systems->create('Folder IS', 'folder-is-unit', null, [], true, $isFolder);
    $reject(fn () => $systems->update($system, 'bad', null, [], true, $root));
    $reject(fn () => (new FolderRepository($db, 1, 'infosystems'))->delete($isFolder));
    $layoutsA = new LayoutRepository($db, 1); $layoutsB = new LayoutRepository($db, $siteB);
    $layoutA = $layoutsA->create('A', 'layouts/folder-layout.php', null);
    $layoutB = $layoutsB->create('B', 'layouts/folder-layout.php', null);
    $assert($layoutA !== $layoutB && $layoutsB->find($layoutA) === null, 'Layouts crossed site boundary');
    $reject(fn () => $db->exec("UPDATE layouts SET parent_id = $layoutA WHERE id = $layoutB"));
    $assert((new SiteAccessRepository($db))->filter([['id' => 1], ['id' => $siteB]], 0) === [], 'Anonymous site membership granted');
    echo "Content folders OK: hierarchy, cycles, duplicate names, site/scope FK enforcement, nonempty deletion, document/IS moves and site-local layouts\n";
} finally { if ($db->inTransaction()) $db->rollBack(); }
