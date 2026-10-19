<?php

declare(strict_types=1);

// Every worker is a fresh process: no already-loaded provider can hide a missing package.
if (($argv[1] ?? '') === '--worker') {
    $root = $argv[2];
    $host = $argv[3];
    $path = $argv[4];
    require $root . '/vendor/autoload.php';
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    if (str_starts_with($path, '/admin') && $path !== '/admin/login') {
        session_start();
        $db = Core\Database::connection();
        $auth = new Core\Security\AuthService(new Core\Repository\UserRepository($db), new Core\Repository\LoginAttemptRepository($db));
        if ($auth->login((string) getenv('G0_FIXTURE_EMAIL'), (string) getenv('G0_FIXTURE_PASSWORD'), '127.0.0.1') !== Core\Security\LoginStatus::Success) {
            throw new RuntimeException('Removal fixture authentication failed.');
        }
    }
    ob_start();
    (new Core\Application($root))->run(new Core\Http\Request('GET', $path, $path, [], [], $_SERVER, []));
    $body = (string) ob_get_clean();
    echo json_encode(['status' => http_response_code(), 'body' => $body], JSON_THROW_ON_ERROR);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';
$db = Core\Database::connection();
$source = dirname(__DIR__);
if (Core\Config::environment() !== 'testing') throw new RuntimeException('Run module-removal fixtures only with APP_ENV=testing and a disposable test database.');
$temporary = sys_get_temp_dir() . '/uv-g0-removal-' . bin2hex(random_bytes(8));
$suffix = bin2hex(random_bytes(6));
$email = 'g0-' . $suffix . '@example.test';
$password = bin2hex(random_bytes(24));
$sites = [];
$user = null;
$copy = static function (string $from, string $to) use (&$copy): void {
    if (is_link($from)) throw new RuntimeException('Unexpected symlink in removal fixture.');
    if (is_dir($from)) {
        if (!is_dir($to) && !mkdir($to, 0775, true)) throw new RuntimeException('Cannot create removal fixture directory.');
        foreach (new DirectoryIterator($from) as $entry) {
            if (!$entry->isDot()) $copy($entry->getPathname(), $to . '/' . $entry->getFilename());
        }
    } elseif (!copy($from, $to)) {
        throw new RuntimeException('Cannot copy removal fixture file.');
    }
};
$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (new DirectoryIterator($path) as $entry) {
            if (!$entry->isDot()) $remove($entry->getPathname());
        }
        rmdir($path);
    } elseif (file_exists($path) || is_link($path)) {
        unlink($path);
    }
};
$insertId = static function (string $sql, array $params) use ($db): int {
    $s = $db->prepare($sql . Core\Database\SqlDialect::returningId($db));
    $s->execute($params);
    return Core\Database\SqlDialect::insertedId($db, $s);
};
try {
    foreach (['a', 'b'] as $variant) {
        $id = $insertId('INSERT INTO sites(code,name) VALUES(:code,:name)', ['code' => 'g0-' . $suffix . '-' . $variant, 'name' => 'G0 ' . $variant]);
        $sites[$variant] = $id;
        $db->prepare('INSERT INTO site_domains(site_id,host,is_primary) VALUES(:site,:host,TRUE)')->execute(['site' => $id, 'host' => 'g0-' . $suffix . '-' . $variant . '.test']);
        $db->prepare("INSERT INTO nodes(site_id,name,slug,path,title,status,page_type,page_config,layout_id) VALUES(:site,'Independent','','/','G0', 'published','g0.fixture','{}',(SELECT id FROM layouts WHERE site_id=:layout_site AND code='main'))")->execute(['site' => $id, 'layout_site' => $id]);
    }
    $user = $insertId('INSERT INTO users(email,password_hash,display_name) VALUES(:email,:hash,:name)', ['email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'name' => 'G0 fixture']);
    $editorDoc = $insertId('INSERT INTO documents(site_id,code,name) VALUES(:site,:code,:name)', ['site' => $sites['a'], 'code' => 'g0-' . $suffix, 'name' => 'Editor removal']);
    $rootStatement = $db->prepare("SELECT id FROM nodes WHERE site_id=:site AND path='/'");
    $rootStatement->execute(['site' => $sites['a']]);
    $editorNode = $insertId("INSERT INTO nodes(site_id,parent_id,name,slug,path,title,status,page_type,page_config) VALUES(:site,:parent,'Editor removal','editor','/editor','Editor','draft','documents.page',:config)",
        ['site' => $sites['a'], 'parent' => (int) $rootStatement->fetchColumn(), 'config' => json_encode(['document' => 'g0-' . $suffix], JSON_THROW_ON_ERROR)]);
    $db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT :user,id FROM roles WHERE code='superadmin'")->execute(['user' => $user]);
    $manifests = [];
    foreach (glob($source . '/modules/*/module.json') ?: [] as $file) {
        $m = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $manifests[$m['code']] = $m;
    }
    $cases = array_merge(['baseline', 'all'], array_keys($manifests));
    $requests = 0;
    foreach ($cases as $case) {
        $root = $temporary . '/' . $case;
        foreach (['core', 'templates', 'public', 'vendor', 'modules'] as $folder) $copy($source . '/' . $folder, $root . '/' . $folder);
        foreach (['cache', 'logs', 'templates'] as $folder) mkdir($root . '/storage/' . $folder, 0775, true);
        // Remove dependent packages too: they are not independent survivors of this scenario.
        $removed = $case === 'all' ? array_keys($manifests) : ($case === 'baseline' ? [] : [$case]);
        do {
            $before = count($removed);
            foreach ($manifests as $code => $manifest) {
                if (!in_array($code, $removed, true) && array_intersect(array_keys($manifest['requires'] ?? []), $removed) !== []) $removed[] = $code;
            }
        } while (count($removed) !== $before);
        foreach ($removed as $code) $remove($root . '/modules/' . $code);
        $fixture = $root . '/modules/g0-fixture';
        mkdir($fixture);
        file_put_contents($fixture . '/module.json', json_encode(['code' => 'g0-fixture', 'name' => 'G0 independent fixture', 'version' => '1.0.0', 'extension_api' => '^1.0', 'default_enabled' => true, 'requires' => ['core' => '>=0.4.0'], 'provider' => 'G0IndependentFixture'], JSON_THROW_ON_ERROR));
        file_put_contents($fixture . '/autoload.php', <<<'FIXTURE'
<?php
final class G0IndependentFixture implements \Core\Extension\ModuleInterface {
    public function register(\Core\Extension\Core $core): void {
        $core->pages()->type(new \Core\Page\PageTypeDefinition(
            code: 'g0.fixture', name: 'G0 fixture',
            executor: new class implements \Core\Page\PageExecutorInterface {
                public function execute(\Core\Page\PageExecutionContext $context): \Core\View\SafeHtml {
                    return \Core\View\SafeHtml::fromTrustedStorage('G0-SITE-' . $context->site->id);
                }
            },
        ));
    }
}
FIXTURE);
        foreach (['/health', '/admin/login', '/admin', '/admin/structure', '/admin/layouts', '/admin/modules', '/admin/structure/' . $editorNode . '/edit', '/'] as $path) {
            foreach ($path === '/' ? ['a', 'b'] : ['a'] as $variant) {
                $env = array_merge(getenv(), ['G0_FIXTURE_EMAIL' => $email, 'G0_FIXTURE_PASSWORD' => $password]);
                $process = proc_open([PHP_BINARY, __FILE__, '--worker', $root, 'g0-' . $suffix . '-' . $variant . '.test', $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $source, $env);
                if (!is_resource($process)) throw new RuntimeException('Cannot start a fresh removal worker.');
                $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
                $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
                $exit = proc_close($process);
                if ($exit !== 0 || $stderr !== '') throw new RuntimeException("Removal worker failed ($case, $path): " . $stderr);
                $result = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
                if ($result['status'] !== 200) throw new RuntimeException("Removal case $case broke $path: " . $result['status']);
                if ($path === '/admin/structure/' . $editorNode . '/edit') {
                    $hasEditor = str_contains($result['body'], '/admin/documents/' . $editorDoc . '/edit?');
                    if ($hasEditor === in_array('documents', $removed, true)) throw new RuntimeException('Editor capability outlived its owner or vanished while enabled.');
                    if (!$hasEditor && !str_contains($result['body'], 'Редактор содержимого недоступен')) throw new RuntimeException('Missing owner has no clear UI state.');
                }
                if ($path === '/' && (!str_contains($result['body'], 'G0-SITE-' . $sites[$variant]) || str_contains($result['body'], 'G0-SITE-' . $sites[$variant === 'a' ? 'b' : 'a']))) throw new RuntimeException('Removal page crossed the site boundary.');
                if ($path === '/admin') {
                    foreach (['documents' => '/admin/documents', 'infosystem' => '/admin/infosystems', 'sites' => '/admin/sites', 'updater' => '/admin/updates'] as $code => $route) {
                        if (in_array($code, $removed, true) && str_contains($result['body'], 'href="' . $route . '"')) throw new RuntimeException('Removed package left live navigation: ' . $code);
                    }
                }
                $requests++;
            }
        }
        $remove($root);
    }
    echo 'MODULE REMOVAL OK: ' . count($cases) . " filesystem scenarios, $requests fresh application dispatches, two-site isolation\n";
} finally {
    foreach ($sites as $id) {
        $db->prepare('DELETE FROM nodes WHERE site_id=:id')->execute(['id' => $id]);
        $db->prepare('DELETE FROM documents WHERE site_id=:id')->execute(['id' => $id]);
        $db->prepare('DELETE FROM layouts WHERE site_id=:id')->execute(['id' => $id]);
        $db->prepare('DELETE FROM sites WHERE id=:id')->execute(['id' => $id]);
    }
    if ($user !== null) {
        $db->prepare('DELETE FROM user_roles WHERE user_id=:id')->execute(['id' => $user]);
        $db->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $user]);
    }
    $remove($temporary);
}
