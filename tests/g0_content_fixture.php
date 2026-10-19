<?php

declare(strict_types=1);

// Only for the dedicated, empty CI database. This is not an installer or migration.
require dirname(__DIR__) . '/vendor/autoload.php';
if (getenv('APP_ENV') !== 'testing' || getenv('G0_DISPOSABLE') !== '1' || getenv('DB_NAME') !== 'cms_g0_perf') {
    throw new RuntimeException('Dedicated disposable G0 database and APP_ENV=testing required.');
}
$db = Core\Database::connection();
if ((int) $db->query('SELECT COUNT(*) FROM nodes')->fetchColumn() !== 0
    || (int) $db->query('SELECT COUNT(*) FROM documents')->fetchColumn() !== 0) {
    throw new RuntimeException('G0 fixture requires a clean migrated database.');
}
$directory = $argv[1] ?? throw new RuntimeException('Fixture output directory required.');
if (!mkdir($directory, 0700, true)) throw new RuntimeException('Fixture directory must be new.');
mkdir($directory . '/sessions', 0700);
$insert = static function (string $sql, array $parameters) use ($db): int {
    $statement = $db->prepare($sql . Core\Database\SqlDialect::returningId($db));
    $statement->execute($parameters);
    return Core\Database\SqlDialect::insertedId($db, $statement);
};
$metadata = ['schema' => 1, 'sites' => []];
$db->beginTransaction();
try {
    foreach (['primary', 'secondary'] as $variant) {
        $site = $insert('INSERT INTO sites(code,name) VALUES(:code,:name)', ['code' => 'g0-' . $variant, 'name' => 'G0 ' . $variant]);
        $host = 'g0-' . $variant . '.test';
        $db->prepare('INSERT INTO site_domains(site_id,host,is_primary) VALUES(:site,:host,TRUE)')->execute(['site' => $site, 'host' => $host]);
        $layout = (int) $db->query('SELECT id FROM layouts WHERE site_id=' . $site . " AND code='main'")->fetchColumn();
        if ($layout < 1) throw new RuntimeException('Site default layout missing.');
        $marker = strtoupper($variant) . '_CONTENT';
        $makeNode = static function (string $name, string $slug, string $path, ?int $parent, string $type, array $config) use ($insert, $site, $layout): int {
            return $insert('INSERT INTO nodes(site_id,name,slug,path,parent_id,title,layout_id,status,page_type,page_config) VALUES(:site,:name,:slug,:path,:parent,:title,:layout,\'published\',:type,:config)', [
                'site' => $site, 'name' => $name, 'slug' => $slug, 'path' => $path, 'parent' => $parent, 'title' => $name,
                'layout' => $layout, 'type' => $type, 'config' => json_encode($config, JSON_THROW_ON_ERROR),
            ]);
        };
        $makeDocument = static function (string $code, string $name) use ($insert, $db, $site, $marker): int {
            $id = $insert('INSERT INTO documents(site_id,code,name) VALUES(:site,:code,:name)', ['site' => $site, 'code' => $code, 'name' => $name]);
            $db->prepare("INSERT INTO document_versions(document_id,version,content,status,published_at) VALUES(:document,1,:content,'published',CURRENT_TIMESTAMP)")
                ->execute(['document' => $id, 'content' => '<p>' . $marker . ' ' . str_repeat('content ', 128) . '</p>']);
            return $id;
        };
        $rootDocument = $makeDocument('home', $marker . ' home');
        $root = $makeNode($marker . ' home', '', '/', null, 'documents.page', ['document' => 'home']);
        $news = $makeNode($marker . ' news', 'news', '/news', $root, 'infosystem.list', ['limit' => 25]);
        $infosystem = $insert('INSERT INTO infosystems(site_id,name,code,field_schema) VALUES(:site,:name,\'news\',:schema)', [
            'site' => $site, 'name' => $marker . ' news', 'schema' => json_encode([
                ['code' => 'kind', 'name' => 'Kind', 'type' => 'select', 'options' => ['news', 'case'], 'filterable' => true],
                ['code' => 'capacity', 'name' => 'Capacity', 'type' => 'number'],
                ['code' => 'featured', 'name' => 'Featured', 'type' => 'boolean'],
            ], JSON_THROW_ON_ERROR),
        ]);
        $db->prepare("INSERT INTO node_module_bindings(node_id,module_code,binding_code,target_key) VALUES(:node,'infosystem','primary','news')")->execute(['node' => $news]);
        $groups = [];
        for ($i = 1; $i <= 20; $i++) {
            $slug = sprintf('group-%02d', $i);
            $groups[$i] = $insert('INSERT INTO infosystem_groups(infosystem_id,name,slug,path,sorting) VALUES(:system,:name,:slug,:path,:sorting)', [
                'system' => $infosystem, 'name' => $marker . ' ' . $slug, 'slug' => $slug, 'path' => '/' . $slug, 'sorting' => $i,
            ]);
        }
        $itemStatement = $db->prepare('INSERT INTO infosystem_items(infosystem_id,group_id,name,slug,path,content,status,is_active,sorting,properties,publish_at) VALUES(:system,:group_id,:name,:slug,:path,:content,:status,:active,:sorting,:properties,\'2025-01-01 00:00:00\')');
        $items = $variant === 'primary' ? 10000 : 20;
        for ($i = 1; $i <= $items; $i++) {
            $group = ($i - 1) % 20 + 1;
            $slug = sprintf('item-%05d', $i);
            $itemStatement->execute([
                'system' => $infosystem, 'group_id' => $groups[$group], 'name' => $marker . ' ' . $slug,
                'slug' => $slug, 'path' => sprintf('/group-%02d/%s', $group, $slug), 'content' => '<p>' . str_repeat('item content ', 48) . '</p>',
                'status' => $i % 5 === 0 ? 'draft' : 'published', 'active' => $i % 7 === 0 ? 0 : 1, 'sorting' => $i,
                'properties' => json_encode(['kind' => $i % 2 === 0 ? 'news' : 'case', 'capacity' => $i, 'featured' => $i % 3 === 0], JSON_THROW_ON_ERROR),
            ]);
        }
        if ($variant === 'primary') {
            $sections = [];
            for ($i = 1; $i <= 10; $i++) {
                $slug = sprintf('section-%02d', $i);
                $makeDocument($slug, $marker . ' ' . $slug);
                $sections[$i] = $makeNode($marker . ' ' . $slug, $slug, '/' . $slug, $root, 'documents.page', ['document' => $slug]);
            }
            // root + news + 10 sections + 988 pages = exactly 1,000 nodes.
            for ($i = 1; $i <= 988; $i++) {
                $section = ($i - 1) % 10 + 1;
                $slug = sprintf('page-%04d', $i);
                $makeDocument($slug, $marker . ' ' . $slug);
                $makeNode($marker . ' ' . $slug, $slug, sprintf('/section-%02d/%s', $section, $slug), $sections[$section], 'documents.page', ['document' => $slug]);
            }
        }
        $metadata['sites'][$variant] = ['id' => $site, 'host' => $host, 'infosystem' => $infosystem, 'document' => $rootDocument, 'marker' => $marker];
    }
    $password = bin2hex(random_bytes(24));
    $email = 'g0-perf@example.test';
    $user = $insert('INSERT INTO users(email,password_hash,display_name) VALUES(:email,:hash,\'G0 fixture\')', ['email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
    $db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT :user,id FROM roles WHERE code='superadmin'")->execute(['user' => $user]);
    $db->commit();
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}
session_save_path($directory . '/sessions');
session_start();
$auth = new Core\Security\AuthService(new Core\Repository\UserRepository($db), new Core\Repository\LoginAttemptRepository($db));
if ($auth->login($email, $password, '127.0.0.1') !== Core\Security\LoginStatus::Success) throw new RuntimeException('G0 authentication failed.');
(new Core\Site\AdminSiteSelector(new Core\Repository\SiteRepository($db)))->select($metadata['sites']['primary']['id']);
$metadata['session'] = session_id();
session_write_close();
$metadata['counts'] = [];
foreach ($metadata['sites'] as $variant => $site) {
    $metadata['counts'][$variant] = [
        'nodes' => (int) $db->query('SELECT COUNT(*) FROM nodes WHERE site_id=' . $site['id'])->fetchColumn(),
        'documents' => (int) $db->query('SELECT COUNT(*) FROM documents WHERE site_id=' . $site['id'])->fetchColumn(),
        'items' => (int) $db->query('SELECT COUNT(*) FROM infosystem_items WHERE infosystem_id=' . $site['infosystem'])->fetchColumn(),
    ];
}
if ($metadata['counts']['primary'] !== ['nodes' => 1000, 'documents' => 999, 'items' => 10000]) throw new RuntimeException('Wrong primary fixture size.');
file_put_contents($directory . '/fixture.json', json_encode($metadata, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
chmod($directory . '/fixture.json', 0600);
echo "G0 fixture: 1000 nodes, 999 documents, 10000 items; second site and real authenticated session\n";
