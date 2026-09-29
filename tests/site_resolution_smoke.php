<?php

declare(strict_types=1);

use Core\Database;
use Core\Http\Request;
use Core\Repository\InfosystemRepository;
use Core\Repository\NodeRepository;
use Core\Repository\SiteRepository;
use Core\Site\SiteResolver;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = Database::connection();
$resolver = new SiteResolver(new SiteRepository($db), 'http://127.0.0.1:8080');

$request = static fn (string $host): Request => new Request(
    method: 'GET',
    uri: '/',
    path: '/',
    query: [],
    post: [],
    server: ['HTTP_HOST' => $host],
    cookies: [],
);

$default = $resolver->resolve($request('127.0.0.1:8080'));
$second = $resolver->resolve($request('second.test'));
$unknown = $resolver->resolve($request('unknown.test'));

if ($default === null || $default->id !== 1 || $default->code !== 'default') {
    throw new RuntimeException('APP_URL host did not resolve the default site.');
}
if ($second === null || $second->code !== 'second' || $second->host !== 'second.test') {
    throw new RuntimeException('Registered host did not resolve the second site.');
}
if ($unknown !== null) {
    throw new RuntimeException('Unknown Host must not fall back to the default site.');
}
if ($resolver->normalizeHost('SECOND.TEST:443') !== 'second.test'
    || $resolver->normalizeHost('evil.test/path') !== null
    || $resolver->normalizeHost('evil.test,proxy.test') !== null) {
    throw new RuntimeException('Host normalization policy failed.');
}

$nodes = new NodeRepository($db);
$defaultNode = $nodes->findPublishedByPath($default->id, '/');
$secondNode = $nodes->findPublishedByPath($second->id, '/');
if (($defaultNode['title'] ?? null) !== 'Ultima Vox CMS'
    || ($secondNode['title'] ?? null) !== 'Second Site') {
    throw new RuntimeException('Site-scoped node resolution failed.');
}

$infosystems = new InfosystemRepository($db);
$defaultCatalog = $infosystems->findActiveByCode($default->id, 'catalog');
$secondCatalog = $infosystems->findActiveByCode($second->id, 'catalog');
if ($defaultCatalog === null || $secondCatalog === null || $defaultCatalog['id'] === $secondCatalog['id']) {
    throw new RuntimeException('Site-scoped infosystem code resolution failed.');
}

$defaultItems = $infosystems->findPublishedItems($default->id, (int) $defaultCatalog['id'], 100, 0, ['kind' => 'bio']);
$secondItems = $infosystems->findPublishedItems($second->id, (int) $secondCatalog['id']);
if (count($defaultItems) !== 1
    || ($defaultItems[0]['name'] ?? null) !== 'Tank 5'
    || count($secondItems) !== 1
    || ($secondItems[0]['name'] ?? null) !== 'Second Tank') {
    throw new RuntimeException('Site-scoped infosystem item resolution failed.');
}

$blocked = false;
$db->beginTransaction();
try {
    $statement = $db->prepare(
        <<<'SQL'
        INSERT INTO nodes (site_id, parent_id, layout_id, name, slug, path, title, status)
        VALUES (
            :site_id,
            :parent_id,
            (SELECT id FROM layouts WHERE template_path = 'layouts/main.html.php'),
            'Cross Site', 'cross-site', '/cross-site', 'Cross Site', 'published'
        )
        SQL
    );
    $statement->execute([
        'site_id' => $second->id,
        'parent_id' => (int) $defaultNode['id'],
    ]);
} catch (PDOException) {
    $blocked = true;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

if (!$blocked) {
    throw new RuntimeException('Cross-site node parent relation was not rejected.');
}

fwrite(STDOUT, "SITE RESOLUTION OK\n");
