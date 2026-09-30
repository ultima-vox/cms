<?php

declare(strict_types=1);

use Core\Database;
use UltimaVox\Modules\Infosystem\Repository\InfosystemRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/modules/infosystem/autoload.php';

$db = Database::connection();
$id = (int) $db->query("SELECT id FROM infosystems WHERE site_id = 1 AND code = 'catalog'")->fetchColumn();
$repo = new InfosystemRepository($db);
$system = $repo->findActiveByCode(1, 'catalog');
$items = $repo->findPublishedItems(1, $id, 100, 0, ['kind' => 'bio']);

if (($system['id'] ?? null) !== $id
    || (int) ($system['site_id'] ?? 0) !== 1
    || count($items) !== 1
    || ($items[0]['name'] ?? null) !== 'Tank 5'
    || (float) ($items[0]['properties']['capacity'] ?? 0) !== 5.0) {
    throw new RuntimeException('JSONB repository smoke test failed.');
}

fwrite(STDOUT, "JSONB OK\n");
