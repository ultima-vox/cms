<?php

declare(strict_types=1);

use Core\Database;
use Core\Repository\InfosystemItemSearchRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = Database::connection();
$id = (int) $db->query("SELECT id FROM infosystems WHERE code = 'catalog'")->fetchColumn();
$repo = new InfosystemItemSearchRepository($db);
$page = $repo->search($id, 2, 25);

if ($page['total'] !== 61 || $page['pages'] !== 3 || $page['page'] !== 2 || count($page['items']) !== 25) {
    throw new RuntimeException('Pagination smoke test failed.');
}

$filtered = $repo->search($id, 1, 25, 'Tank', null, 'published', ['kind' => 'bio']);
if ($filtered['total'] !== 1 || ($filtered['items'][0]['name'] ?? null) !== 'Tank 5') {
    throw new RuntimeException('Search/filter smoke test failed.');
}

fwrite(STDOUT, "ADMIN SEARCH OK\n");
