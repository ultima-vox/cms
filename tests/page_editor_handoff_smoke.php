<?php

declare(strict_types=1);

use Core\Database;
use Core\Database\SqlDialect;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core;
use Core\Extension\ModuleLoader;
use Core\Extension\VersionConstraint;
use Core\Page\PageEditorLinkProviderInterface;
use Core\Page\PageEditorNavigation;
use Core\Page\PageTypeDefinition;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\StructureRepository;
use Core\Repository\UserRepository;
use Core\Security\AuthService;
use Core\Security\LoginStatus;
use Core\Site\SiteContext;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;
use UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository;
use UltimaVox\Modules\Sites\SitesManagementRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
foreach (['documents', 'infosystem', 'sites'] as $module) require dirname(__DIR__) . '/modules/' . $module . '/autoload.php';
session_start();
$db = Database::connection();
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$insert = static function (string $sql, array $params = []) use ($db): int {
    $statement = $db->prepare($sql . SqlDialect::returningId($db));
    $statement->execute($params);
    return SqlDialect::insertedId($db, $statement);
};
$db->beginTransaction();
try {
    $site = (new SitesManagementRepository($db))->create('Editor A', 'handoff-a');
    $other = (new SitesManagementRepository($db))->create('Editor B', 'handoff-b');
    $password = bin2hex(random_bytes(24));
    $user = $insert('INSERT INTO users(email,password_hash,display_name) VALUES(:email,:hash,:name)',
        ['email' => 'handoff@example.test', 'hash' => password_hash($password, PASSWORD_DEFAULT), 'name' => 'Handoff fixture']);
    $db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT :user,id FROM roles WHERE code='superadmin'")->execute(['user' => $user]);
    $auth = new AuthService(new UserRepository($db), new LoginAttemptRepository($db));
    $assert($auth->login('handoff@example.test', $password, '127.0.0.1') === LoginStatus::Success, 'Fixture login failed');
    $core = new Core(new RuntimeApi($db, dirname(__DIR__), new SiteContext($site, 'handoff-a', 'A', 'handoff-a.test')));
    (new ModuleLoader(dirname(__DIR__)))->load($core);
    $structure = new StructureRepository($db, $site);
    $docs = new DocumentRepository($db);
    $doc = $docs->create($site, 'shared-code', 'Local document');
    $foreignDoc = $docs->create($other, 'shared-code', 'Foreign document');
    $base = ['parent_id' => null, 'layout_id' => null, 'name' => 'Editor fixture', 'slug' => '', 'title' => 'Editor fixture',
        'meta_description' => null, 'status' => 'draft', 'is_active' => true, 'sorting' => 0, 'publish_at' => null];
    $node = $structure->create($base, 'documents.page', ['document' => 'shared-code']);
    $record = $structure->find($node);
    $url = '/admin/documents/' . $doc . '/edit';
    $assert($core->pages()->editorUrl($record, $auth) === $url, 'Document owner resolved the wrong site/code');
    $navigation = new PageEditorNavigation($core->pages(), $structure, $auth, $site);
    $context = ['_page' => (string) $node, '_page_site' => (string) $site];
    $assert($navigation->returnContext($context, $url)['url'] === '/admin/structure/' . $node . '/edit', 'Valid return lost');
    foreach ([['_page_site' => (string) $other], ['_page' => []], ['_page' => 'https://example.test'], ['_page' => '999999999999999999']] as $bad) {
        $assert($navigation->returnContext(array_replace($context, $bad), $url) === null, 'Malformed/stale return accepted');
    }
    $assert($navigation->returnContext($context, '/admin/documents/' . $foreignDoc . '/edit') === null, 'Unrelated editor accepted');
    $foreignRecord = $record; $foreignRecord['site_id'] = $other;
    $assert($core->pages()->editorUrl($foreignRecord, $auth) === null, 'Provider accepted another site');
    $missing = $record; $missing['page_config'] = '{"document":"missing"}';
    $assert($core->pages()->editorUrl($missing, $auth) === null, 'Missing document left a link');
    $missing['page_config'] = '{broken';
    $assert($core->pages()->editorUrl($missing, $auth) === null, 'Malformed configuration broke optional capability');
    $missing['page_type'] = 'missing.owner';
    $assert($core->pages()->editorUrl($missing, $auth) === null, 'Missing owner left a link');
    $systems = new InfosystemManagementRepository($db, $site);
    $system = $systems->create('Local IS', 'shared-system', null, [], true);
    (new InfosystemManagementRepository($db, $other))->create('Foreign IS', 'shared-system', null, [], true);
    $item = $systems->saveItem(null, $system, null, ['name' => 'Draft item', 'slug' => 'draft-item', 'description' => null,
        'content' => '', 'meta_description' => null, 'status' => 'draft', 'is_active' => true, 'sorting' => 0, 'properties' => [], 'publish_at' => null]);
    $isRecord = $record; $isRecord['page_type'] = 'infosystem.list'; $isRecord['page_config'] = '{}';
    $db->prepare("INSERT INTO node_module_bindings(node_id,module_code,binding_code,target_key) VALUES(:node,'infosystem','primary','shared-system')")->execute(['node' => $node]);
    $assert($core->pages()->editorUrl($isRecord, $auth) === '/admin/infosystems/' . $system, 'List must link to owner management, not an invented item');
    $isRecord['page_type'] = 'infosystem.item'; $isRecord['page_config'] = '{"item_path":"/draft-item"}';
    $assert($core->pages()->editorUrl($isRecord, $auth) === '/admin/infosystems/' . $system . '/items/' . $item . '/edit', 'Item editor must resolve actual same-site path, including drafts');
    $isRecord['page_config'] = '{"item_path":"/missing"}';
    $assert($core->pages()->editorUrl($isRecord, $auth) === null, 'Missing item left a link');
    $isRecord['page_config'] = '{"item_path":"/draft-item"}';
    $role = $insert("INSERT INTO roles(code,name) VALUES('handoff-structure-only','Structure only')");
    $db->prepare("INSERT INTO role_permissions(role_id,permission_id) SELECT :role,id FROM permissions WHERE code='structure.manage'")->execute(['role' => $role]);
    $db->prepare('DELETE FROM user_roles WHERE user_id=:user')->execute(['user' => $user]);
    $db->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(:user,:role)')->execute(['user' => $user, 'role' => $role]);
    $assert($core->pages()->editorUrl($record, $auth) === null && $core->pages()->editorUrl($isRecord, $auth) === null, 'Owner permission missing but editor visible');
    $db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT :user,id FROM roles WHERE code='superadmin'")->execute(['user' => $user]);
    foreach (['https://example.test', '//example.test', '/admin/../outside', '/admin/path?return=bad', "/admin/path\r\nX-Test: bad"] as $unsafe) {
        $provider = new class($unsafe) implements PageEditorLinkProviderInterface {
            public function __construct(private string $url) {}
            public function editorUrl(array $node, AuthService $auth): ?string { return $this->url; }
        };
        $type = 'fixture.' . md5($unsafe);
        $core->pages()->type(new PageTypeDefinition($type, 'Unsafe', $core->pages()->resolve('documents.page'), editorLinkProvider: $provider));
        $bad = $record; $bad['page_type'] = $type;
        $assert($core->pages()->editorUrl($bad, $auth) === null, 'Unsafe destination accepted');
    }
    $assert(VersionConstraint::matches('1.1.0', '^1.0') && !VersionConstraint::matches('0.4.0', '>=0.5.0'), 'Additive API constraints broken');
} finally {
    ($auth ?? null)?->logout();
    if ($db->inTransaction()) $db->rollBack();
}
echo "PAGE EDITOR HANDOFF OK: owner paths, same-code site isolation, real IS list/item targets, permissions, missing owners/content, bounded returns and safe destinations\n";
