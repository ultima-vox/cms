<?php

declare(strict_types=1);

use Core\Content\FolderRepository;
use Core\Database;
use Core\Database\SqlDialect;
use Core\Repository\LayoutRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
session_start();
$db = Database::connection();
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$db->beginTransaction();
try {
    $insert = $db->query("INSERT INTO sites(code,name) VALUES('layout-list-a','List A')" . SqlDialect::returningId($db));
    $site = SqlDialect::insertedId($db, $insert);
    $insert = $db->query("INSERT INTO sites(code,name) VALUES('layout-list-b','List B')" . SqlDialect::returningId($db));
    $other = SqlDialect::insertedId($db, $insert);
    $folder = (new FolderRepository($db, $site, 'layouts'))->save(null, 'Макеты', null);
    $repo = new LayoutRepository($db, $site);
    // New sites provision their own system layout; measure it before adding this fixture.
    $baselineRoot = $repo->page([])['total'];
    $baselineAll = $repo->page([], null, true)['total'];
    for ($i = 1; $i <= 61; $i++) {
        $code = 'list-' . sprintf('%03d', $i);
        $repo->create('Макет ' . sprintf('%03d', $i), 'layouts/' . $code . '.php', null, $folder);
    }
    $repo->create('Корневой макет', 'layouts/list-root.php', null);
    $repo->create('Ёжик 100%_!', 'layouts/list-special.php', null, $folder);
    $foreign = $repo->forSite($other)->create('Другой сайт', 'layouts/list-001.php', null);
    $first = $repo->page([], $folder);
    $second = $repo->page(['page' => '2'], $folder);
    $assert($first['total'] === 62 && $first['pages'] === 3 && count($first['rows']) === 25, 'Bounded list failed');
    $assert(array_intersect(array_column($first['rows'], 'id'), array_column($second['rows'], 'id')) === [], 'Pages overlap');
    $assert($repo->page([])['total'] === $baselineRoot + 1, 'Root leaked folder layouts');
    $all = $repo->page(['per_page' => '100'], null, true);
    $assert($all['total'] === $baselineAll + 63 && count($all['rows']) === $baselineAll + 63 && $repo->find($foreign) === null, 'Site isolation failed');
    $assert(count($repo->all()) === $baselineAll + 63, 'Internal template selectors changed');
    foreach (['100%_!', '%', '_', '!', 'ёжик', 'list-061'] as $search) {
        $assert($repo->page(['q' => $search], $folder)['total'] === 1, 'Literal/Unicode/code search failed');
    }
    $last = $repo->page(['page' => '999999', 'sort' => 'code'], $folder);
    $assert($last['page'] === 3 && count($last['rows']) === 12, 'Page clamp failed');
    $large = $repo->page(['page' => str_repeat('9', 100), 'sort' => 'name;DROP TABLE layouts', 'q' => [], 'per_page' => []], $folder);
    $assert($large['page'] === 1 && $large['per_page'] === 25 && $large['filters']['q'] === '' && $large['filters']['sort'] === 'name', 'Malformed inputs accepted');
    $assert(count($repo->page(['per_page' => '50'], $folder)['rows']) === 50, 'Page size 50 failed');
    $empty = $repo->page(['q' => 'missing'], $folder);
    $assert($empty['total'] === 0 && $empty['page'] === 1 && $empty['rows'] === [], 'Empty search failed');
    $tieA = $repo->create('Tie', 'layouts/tie-a.php', null, $folder);
    $tieB = $repo->create('Tie', 'layouts/tie-b.php', null, $folder);
    $assert(array_map('intval', array_column($repo->page(['q' => 'Tie'], $folder)['rows'], 'id')) === [$tieA, $tieB], 'Unstable equal-name order');
    $assert(array_map('intval', array_column($repo->page(['q' => 'Tie', 'sort' => 'name_desc'], $folder)['rows'], 'id')) === [$tieB, $tieA], 'Unstable reverse order');
    foreach ([new FolderRepository($db, $other, 'layouts'), new FolderRepository($db, $site, 'documents')] as $folders) {
        $foreignFolder = $folders->save(null, 'Foreign', null);
        $failed = false;
        try { $repo->page([], $foreignFolder); } catch (RuntimeException) { $failed = true; }
        $assert($failed, 'Foreign site/scope folder accepted');
    }
    // A content editor without trusted-template permission must never reach list/render queries.
    $password = bin2hex(random_bytes(24));
    $q = $db->prepare('INSERT INTO users(email,password_hash,display_name) VALUES(:email,:hash,:name)' . SqlDialect::returningId($db));
    $q->execute(['email' => 'layout-list@example.test', 'hash' => password_hash($password, PASSWORD_DEFAULT), 'name' => 'Editor']);
    $user = SqlDialect::insertedId($db, $q);
    $db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT :user,id FROM roles WHERE code='editor'")->execute(['user' => $user]);
    $auth = new \Core\Security\AuthService(new \Core\Repository\UserRepository($db), new \Core\Repository\LoginAttemptRepository($db));
    $assert($auth->login('layout-list@example.test', $password, '127.0.0.1') === \Core\Security\LoginStatus::Success, 'Fixture login failed');
    $controller = new \Core\Controller\LayoutController($auth, $repo, new \Core\Layout\LayoutTemplateService(dirname(__DIR__), $site),
        new \Core\Repository\AuditLogRepository($db, $site), new \Core\View\AdminPhpRenderer(dirname(__DIR__)), new FolderRepository($db, $site, 'layouts'));
    $response = $controller->index(new \Core\Http\Request('GET', '/admin/layouts', '/admin/layouts', ['folder' => 'all'], [], [], []));
    $assert($response->statusCode() === 403, 'Untrusted editor reached layouts');
    echo "LAYOUT LIST OK: bounded SQL, literal search, stable order, folders/site isolation, malformed input, trusted permission\n";
} finally { if ($db->inTransaction()) $db->rollBack(); }
