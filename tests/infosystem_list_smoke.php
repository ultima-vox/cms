<?php

declare(strict_types=1);

use Core\Content\FolderRepository;
use Core\Database;
use Core\Database\SqlDialect;
use UltimaVox\Modules\Infosystem\Admin\InfosystemListQuery;
use UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/modules/infosystem/autoload.php';
$db = Database::connection();
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$db->beginTransaction();
try {
    $insert = $db->query("INSERT INTO sites(code,name) VALUES('is-list-a','List A')" . SqlDialect::returningId($db));
    $site = SqlDialect::insertedId($db, $insert);
    $insert = $db->query("INSERT INTO sites(code,name) VALUES('is-list-b','List B')" . SqlDialect::returningId($db));
    $other = SqlDialect::insertedId($db, $insert);
    $folder = (new FolderRepository($db, $site, 'infosystems'))->save(null, 'Каталоги', null);
    $repo = new InfosystemManagementRepository($db, $site);
    for ($i = 1; $i <= 61; $i++) {
        $repo->create('Каталог ' . sprintf('%03d', $i), 'list-' . sprintf('%03d', $i), null, [], $i % 2 === 0, $folder);
    }
    $repo->create('Корневой каталог', 'list-root', null, [], true);
    $repo->create('Ёжик 100%_!', 'list-special', null, [], true, $folder);
    $foreign = (new InfosystemManagementRepository($db, $other))->create('Другой сайт', 'list-001', null, [], true);
    $q = static fn (array $params = [], ?int $selected = null, bool $all = false) => InfosystemListQuery::fromQuery($params, $selected, $all);
    $first = $repo->page($q([], $folder));
    $page = $repo->page($q(['page' => '2'], $folder));
    $assert($page['total'] === 62 && $page['pages'] === 3 && count($page['rows']) === 25, 'Folder pagination count failed');
    $assert(array_intersect(array_column($first['rows'], 'id'), array_column($page['rows'], 'id')) === [], 'Pages overlap');
    $assert($repo->page($q())['total'] === 1, 'Root leaked folder materials');
    $all = $repo->page($q([], null, true));
    $assert($all['total'] === 63 && !in_array($foreign, array_map('intval', array_column($all['rows'], 'id')), true), 'Cross-site leak');
    $assert($repo->page($q(['q' => '100%_!'], $folder))['total'] === 1, 'Search wildcard characters were not literal');
    $assert($repo->page($q(['q' => 'ёжик'], $folder))['total'] === 1, 'Unicode case-insensitive search failed');
    $assert($repo->page($q(['q' => 'list-061'], $folder))['total'] === 1, 'Code search failed');
    $assert($repo->page($q(['status' => 'active'], $folder))['total'] === 31, 'Active filter failed');
    $assert($repo->page($q(['status' => 'inactive'], $folder))['total'] === 31, 'Inactive filter failed');
    $last = $repo->page($q(['page' => '999999', 'sort' => 'code'], $folder));
    $assert($last['page'] === 3 && count($last['rows']) === 12, 'Page clamp failed');
    $desc = $repo->page($q(['sort' => 'name_desc'], $folder));
    $assert((int) $desc['rows'][0]['id'] !== (int) $first['rows'][0]['id'], 'Descending sort failed');
    $invalid = $q(['q' => [], 'page' => [], 'per_page' => '1000000', 'sort' => 'name; DROP TABLE infosystems', 'status' => []], $folder);
    $assert($invalid->search === '' && $invalid->page === 1 && $invalid->perPage === 25 && $invalid->sort === 'name' && $invalid->status === '', 'Malformed inputs accepted');
    $assert($repo->page($q(['q' => 'missing'], $folder))['total'] === 0, 'Empty search failed');
    $tieA = $repo->create('Tie', 'tie-a', null, [], true, $folder);
    $tieB = $repo->create('Tie', 'tie-b', null, [], true, $folder);
    $ties = $repo->page($q(['q' => 'Tie'], $folder));
    $assert(array_map('intval', array_column($ties['rows'], 'id')) === [$tieA, $tieB], 'Equal names lost deterministic ID order');
    $ties = $repo->page($q(['q' => 'Tie', 'sort' => 'name_desc'], $folder));
    $assert(array_map('intval', array_column($ties['rows'], 'id')) === [$tieB, $tieA], 'Equal names lost reverse ID order');
    $foreignFolder = (new FolderRepository($db, $other, 'infosystems'))->save(null, 'Foreign', null);
    $failed = false;
    try { $repo->page($q([], $foreignFolder)); } catch (RuntimeException) { $failed = true; }
    $assert($failed, 'Foreign folder accepted');
    parse_str((string) parse_url($q(['q' => '<script>&"', 'status' => 'active', 'per_page' => '50'], $folder)->url(2), PHP_URL_QUERY), $params);
    $assert($params['folder'] === (string) $folder && $params['q'] === '<script>&"' && $params['page'] === '2' && $params['status'] === 'active', 'Pagination lost filter context');
    echo "INFOSYSTEM LIST OK: pagination, folders, site isolation, search, activity, malformed inputs, links\n";
} finally { if ($db->inTransaction()) $db->rollBack(); }
