<?php

declare(strict_types=1);

use Core\Bootstrap\BuiltinExtensions;
use Core\Database;
use Core\Database\SqlDialect;
use Core\Extension\Api\AdminApi;
use Core\Extension\Api\AdminNavigationItem;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core;
use Core\Extension\ModuleLoader;
use Core\Extension\VersionConstraint;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\UserRepository;
use Core\Security\AuthService;
use Core\Security\LoginStatus;
use Core\View\AdminShellContext;

require dirname(__DIR__) . '/vendor/autoload.php';
session_start();
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$api = new AdminApi();
$api->navigation('legacy', 'Old module', '/admin/legacy');
$api->navigation('explicit', 'Explicit', '/admin/explicit', null, 10, 'module', 'content');
$assert($api->items()[1]->section === 'services', 'Old call lost compatible fallback');
$assert((new AdminNavigationItem('old', 'Old', '/admin/old', null, 1))->section === 'services', 'Old constructor broke');
foreach (['unknown', '', '../system', '<script>'] as $section) {
    try {
        $api->navigation('bad', 'Bad', '/admin/bad', section: $section);
    } catch (RuntimeException) {
        continue;
    }
    throw new RuntimeException('Unknown navigation section accepted');
}
$api->freeze();
try {
    $api->navigation('late', 'Late', '/admin/late', section: 'content');
    throw new RuntimeException('Frozen navigation accepted registration');
} catch (LogicException) {
}
$assert(VersionConstraint::matches('1.1.0', '^1.0') && VersionConstraint::matches('1.2.0', '^1.0')
    && !VersionConstraint::matches('0.5.0', '>=0.6.0'), 'Additive API/Core constraints are incompatible');
$db = Database::connection();
$insert = static function (string $sql, array $params) use ($db): int {
    $q = $db->prepare($sql . SqlDialect::returningId($db));
    $q->execute($params);
    return SqlDialect::insertedId($db, $q);
};
$db->beginTransaction();
try {
    $password = bin2hex(random_bytes(24));
    $user = $insert('INSERT INTO users(email,password_hash,display_name) VALUES(:email,:hash,:name)',
        ['email' => 'nav@example.test', 'hash' => password_hash($password, PASSWORD_DEFAULT), 'name' => 'Navigation fixture']);
    $role = $insert('INSERT INTO roles(code,name) VALUES(:code,:name)', ['code' => 'nav-fixture', 'name' => 'Navigation fixture']);
    $db->prepare("INSERT INTO role_permissions(role_id,permission_id) SELECT :role,id FROM permissions WHERE code='documents.manage'")->execute(['role' => $role]);
    $db->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(:user,:role)')->execute(['user' => $user, 'role' => $role]);
    $auth = new AuthService(new UserRepository($db), new LoginAttemptRepository($db));
    $assert($auth->login('nav@example.test', $password, '127.0.0.1') === LoginStatus::Success, 'Fixture login failed');
    $core = new Core(new RuntimeApi($db, dirname(__DIR__)));
    (new BuiltinExtensions())->register($core);
    (new ModuleLoader(dirname(__DIR__)))->load($core);
    $expected = ['structure' => 'content', 'layouts' => 'design', 'modules' => 'system',
        'documents' => 'content', 'infosystems' => 'content', 'sites' => 'system', 'updates' => 'system'];
    foreach ($core->admin()->items() as $item) {
        $assert(($expected[$item->code] ?? null) === $item->section, 'Owner section registration changed: ' . $item->code);
        unset($expected[$item->code]);
    }
    $assert($expected === [], 'An official owner is missing');
    $core->admin()->navigation('old-extension', 'Legacy extension', '/admin/legacy');
    $shell = new AdminShellContext($auth, $core->admin(), $core->sites());
    $sections = $shell->navigationSections();
    $assert(array_column($sections, 'code') === ['content', 'services'], 'Forbidden/empty sections leaked');
    $assert(array_column($sections[0]['items'], 'code') === ['documents'], 'Permission filtering was bypassed');
    $assert(array_column($sections[1]['items'], 'code') === ['old-extension'], 'Old extension became inaccessible');
    $sites = new \UltimaVox\Modules\Sites\SitesManagementRepository($db);
    $site = $sites->create('Navigation allowed site', 'nav-allowed');
    $other = $sites->create('Navigation forbidden site', 'nav-forbidden');
    $db->prepare('INSERT INTO user_sites(user_id,site_id) VALUES(:user,:site)')->execute(['user' => $user, 'site' => $site]);
    $shell = new AdminShellContext($auth, $core->admin(), new \Core\Extension\Api\SitesApi(
        new \Core\Repository\SiteRepository($db), new \Core\Site\SiteContext($site, 'nav-allowed', 'Navigation allowed site', ''),
    ));
    $renderer = new \Core\View\AdminPhpRenderer(dirname(__DIR__));
    $renderer->addGlobal('admin_shell', $shell);
    foreach (['documents' => 'Контент', 'old-extension' => 'Сервисы', 'dashboard' => 'Обзор'] as $active => $label) {
        $html = $renderer->render('admin/_header.php', ['admin_active' => $active]);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $primary = '//nav[@aria-label="Основная навигация"]';
        $local = '//nav[@aria-label="Меню раздела"]';
        $assert($xpath->query($primary . '//a')->length === 3, 'Forbidden/empty primary section rendered');
        $assert($xpath->query($local . '//a')->length === 1, 'Local navigation contains other sections');
        $assert($xpath->evaluate('string(' . $primary . '//a[@aria-current])') === $label, 'Direct route lost active section');
        $assert(!str_contains($html, 'Navigation forbidden site'), 'Forbidden site name leaked into shell');
        foreach (['/admin/modules', '/admin/updates', '/admin/layouts', '/admin/infosystems'] as $forbidden) {
            $assert(!str_contains($html, 'href="' . $forbidden . '"'), 'Forbidden owner rendered');
        }
    }
    $footer = $renderer->render('admin/_footer.php');
    $assert(str_contains($footer, 'href="/admin/documents"') && str_contains($footer, 'href="/admin/legacy"'), 'Palette lost cross-section legacy links');

} finally {
    ($auth ?? null)?->logout();
    if ($db->inTransaction()) $db->rollBack();
}
echo "ADMIN NAVIGATION OK: compatible calls/constructor, validation/freeze, owner metadata, real permissions, empty sections and legacy fallback\n";
