<?php

declare(strict_types=1);

use Core\Content\FolderRepository;
use Core\Database;
use Core\Database\SqlDialect;
use UltimaVox\Modules\Documents\Admin\DocumentListQuery;
use UltimaVox\Modules\Documents\Repository\DocumentManagementRepository;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/modules/documents/autoload.php';
$db = Database::connection();
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$db->beginTransaction();
try {
    $insert = $db->query("INSERT INTO sites(code,name) VALUES('document-list-a','List A')" . SqlDialect::returningId($db));
    $site = SqlDialect::insertedId($db, $insert);
    $insert = $db->query("INSERT INTO sites(code,name) VALUES('document-list-b','List B')" . SqlDialect::returningId($db));
    $other = SqlDialect::insertedId($db, $insert);
    $folder = (new FolderRepository($db, $site, 'documents'))->save(null, 'Материалы', null);
    $documents = new DocumentRepository($db);
    $repo = new DocumentManagementRepository($db, $site);
    $ids = [];
    for ($i = 1; $i <= 61; $i++) {
        $id = $documents->create($site, 'list-' . sprintf('%03d', $i), 'Материал ' . sprintf('%03d', $i));
        $repo->move($id, $folder);
        $ids[] = $id;
    }
    $root = $documents->create($site, 'list-root', 'Корневой документ');
    $foreign = $documents->create($other, 'list-001', 'Другой сайт');
    $special = $documents->create($site, 'list-special', 'Ёжик 100%_!');
    $repo->move($special, $folder);
    $version = $documents->createDraftVersion($ids[0], '<p>Published</p>');
    $documents->publishVersion($ids[0], $version);
    $documents->createDraftVersion($ids[0], '<p>Draft</p>');
    $q = static fn (array $params = [], ?int $selected = null, bool $all = false) => DocumentListQuery::fromQuery($params, $selected, $all);
    $page = $repo->page($q(['page' => '2'], $folder));
    $assert($page['total'] === 62 && $page['pages'] === 3 && count($page['rows']) === 25, 'Folder pagination count failed');
    $first = $repo->page($q([], $folder));
    $assert(array_intersect(array_column($first['rows'], 'id'), array_column($page['rows'], 'id')) === [], 'Pages overlap');
    $assert($repo->page($q())['total'] === 1, 'Root leaked folder materials');
    $all = $repo->page($q([], null, true));
    $assert($all['total'] === 63 && !in_array($foreign, array_map('intval', array_column($all['rows'], 'id')), true), 'All-materials crossed site boundary');
    $assert($repo->page($q(['q' => '100%_!'], $folder))['total'] === 1, 'Search wildcard characters were not literal');
    $assert($repo->page($q(['q' => 'ёжик'], $folder))['total'] === 1, 'Cyrillic case-insensitive search failed');
    $assert($repo->page($q(['q' => 'list-061'], $folder))['total'] === 1, 'Stable code search failed');
    $assert($repo->page($q(['status' => 'published'], $folder))['total'] === 1, 'Publication filter failed');
    $assert($repo->page($q(['status' => 'draft'], $folder))['total'] === 1, 'Draft filter excluded published material with new draft');
    $assert($repo->page($q(['status' => 'unpublished'], $folder))['total'] === 61, 'Unpublished filter failed');
    $last = $repo->page($q(['page' => '999999', 'sort' => 'code'], $folder));
    $assert($last['page'] === 3 && count($last['rows']) === 12, 'Out-of-range page was not clamped');
    $invalid = $q(['q' => [], 'page' => [], 'per_page' => '1000000', 'sort' => 'name; DROP TABLE documents', 'status' => []], $folder);
    $assert($invalid->search === '' && $invalid->page === 1 && $invalid->perPage === 25 && $invalid->sort === 'name' && $invalid->status === '', 'Malformed list inputs accepted');
    $assert($repo->page($q(['q' => 'missing'], $folder))['total'] === 0, 'Empty search count failed');
    $foreignFolder = (new FolderRepository($db, $other, 'documents'))->save(null, 'Foreign list folder', null);
    $failed = false;
    try { $repo->page($q([], $foreignFolder)); } catch (RuntimeException) { $failed = true; }
    $assert($failed, 'List accepted a foreign site folder');
    $url = $q(['q' => '<script>&"', 'status' => 'draft', 'sort' => 'code', 'per_page' => '50'], $folder)->url(2);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
    $assert($params['folder'] === (string) $folder && $params['q'] === '<script>&"' && $params['page'] === '2' && $params['status'] === 'draft', 'Pagination lost encoded filter context');
    echo "DOCUMENT LIST OK: bounded pages, folder/site isolation, literal Unicode/code search, publication filters, malformed inputs, preserved links\n";
} finally { if ($db->inTransaction()) $db->rollBack(); }
