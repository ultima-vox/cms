<?php

declare(strict_types=1);

use Core\Database;
use Core\MigrationRunner;
use Core\Http\DeploymentGuard;
use UltimaVox\Modules\Updater\FileUpdater;
use UltimaVox\Modules\Updater\UpdateState;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/modules/updater/autoload.php';

// This test runs against a disposable database, never against the application test DB.
if (getenv('DB_NAME') !== 'cms_legacy') throw new RuntimeException('Disposable update database required.');
$db = Database::connection();
(new MigrationRunner($db, dirname(__DIR__) . '/database/migrations'))->migrate();
function verify(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function store(string $path, string $bytes): void { if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true); file_put_contents($path, $bytes); }
function updateBundle(array $files): array {
    $path = tempnam(sys_get_temp_dir(), 'update-package-');
    $hashes = array_map(static fn ($v) => hash('sha256', $v), $files);
    $schema = array_filter($hashes, static fn ($p) => str_ends_with($p, '.sql'), ARRAY_FILTER_USE_KEY);
    $zip = new ZipArchive(); $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('update.json', json_encode(['version' => '0.1.2', 'files' => $hashes, 'schema' => $schema]));
    foreach ($files as $name => $value) $zip->addFromString('payload/' . $name, $value);
    $zip->close(); $bytes = file_get_contents($path); unlink($path);
    return [$bytes, ['version' => '0.1.2', 'sha256' => hash('sha256', $bytes)]];
}
$base = sys_get_temp_dir() . '/cms-module-update-' . bin2hex(random_bytes(5));
$root = $base . '/application';
$manifest = json_encode(['code' => 'update-fixture', 'name' => 'Fixture', 'version' => '1.0.0', 'extension_api' => '^1.0', 'default_enabled' => true, 'requires' => ['core' => '>=0.2.0'], 'provider' => 'UpdateFixtureProvider']);
$provider = '<?php final class UpdateFixtureProvider implements \\Core\\Extension\\ModuleInterface { public function register(\\Core\\Extension\\Core $core): void { $core->permissions()->define("fixture.update", "Fixture update"); } }';
$old = ['core/state.php' => 'old-code', 'modules/update-fixture/module.json' => $manifest, 'modules/update-fixture/autoload.php' => $provider];
foreach ($old as $name => $bytes) store($root . '/' . $name, $bytes);
store($root . '/release.json', json_encode(['version' => '0.1.1', 'layout' => 'public', 'files' => array_map(static fn ($v) => hash('sha256', $v), $old), 'schema' => new stdClass()]));
$engine = new FileUpdater($root, $db);
$files = array_replace($old, ['core/state.php' => 'new-code',
    'database/migrations/mysql/999_update_fixture.sql' => 'CREATE TABLE update_core_fixture (id integer); INSERT INTO update_core_fixture VALUES (1);',
    'modules/update-fixture/migrations/mysql/001_fixture.sql' => 'CREATE TABLE update_module_fixture (id integer); INSERT INTO update_module_fixture VALUES (2);']);
try {
    [$bad, $badOffer] = updateBundle(array_replace($files, ['modules/update-fixture/migrations/mysql/001_fixture.sql' => 'CREATE TABLE update_module_fixture (id integer); SELECT nonexistent_update_function();']));
    $failed = false;
    try { $engine->apply($bad, $badOffer); } catch (Throwable) { $failed = true; }
    verify($failed, 'Broken migration accepted');
    verify(!\Core\Database\SqlDialect::tableExists($db, 'update_core_fixture'), 'Core migration was not rolled back');
    verify(!\Core\Database\SqlDialect::tableExists($db, 'update_module_fixture'), 'Module migration was not rolled back');
    verify(file_get_contents($root . '/core/state.php') === 'old-code', 'Files were not rolled back');
    verify($engine->current()['version'] === '0.1.1', 'Failed update changed metadata');
    verify(!is_file($root . '/storage/updates/maintenance.json'), 'Failed update left maintenance');
    verify((new UpdateState($root))->history()[0]['status'] === 'rolled_back', 'Rollback missing from history');
    [$bytes, $offer] = updateBundle($files);
    verify(DeploymentGuard::enter($root), 'Cannot enter request guard');
    // FileUpdater must release its own request lease, while still excluding other requests.
    $backup = $engine->apply($bytes, $offer);
    (new \Core\Extension\PendingExtensionSync($db, $root))->run();
    verify(DeploymentGuard::held($root), 'Request lease not restored after installation');
    DeploymentGuard::release($root);
    verify($db->query('SELECT id FROM update_core_fixture')->fetchColumn() === 1, 'Core migration absent');
    verify($db->query('SELECT id FROM update_module_fixture')->fetchColumn() === 2, 'Module migration absent');
    verify($db->query("SELECT version FROM installed_modules WHERE code = 'update-fixture'")->fetchColumn() === '1.0.0', 'Module metadata not synchronized');
    verify($db->query("SELECT code FROM permissions WHERE code = 'fixture.update'")->fetchColumn() === 'fixture.update', 'Permissions not synchronized');
    verify($engine->current()['version'] === '0.1.2', 'Committed version missing');
    $journal = json_decode(file_get_contents($backup . '/journal.json'), true);
    verify(isset($journal['mysql_snapshot_sha256'], $journal['db_commit_token']), 'Legacy snapshot/commit metadata missing');
    // Emulate a process dying immediately after COMMIT, before clearing maintenance.
    store($root . '/storage/updates/maintenance.json', json_encode(['backup' => basename($backup)]));
    store($root . '/bin/update-recover.php', file_get_contents(dirname(__DIR__) . '/bin/update-recover.php'));
    store($root . '/vendor/autoload.php', '<?php require ' . var_export(dirname(__DIR__) . '/vendor/autoload.php', true) . ';');
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/bin/update-recover.php') . ' ' . escapeshellarg(basename($backup)) . ' 2>&1', $output, $status);
    verify($status === 0 && !is_file($root . '/storage/updates/maintenance.json'), 'Committed recovery failed: ' . implode("\n", $output));
    verify(file_get_contents($root . '/core/state.php') === 'new-code', 'Recovery undid committed code');
    // Emulate a crash with no durable commit marker; the snapshot must restore DDL and data.
    $journal['db_commit_token'] = str_repeat('0', 32);
    store($backup . '/journal.json', json_encode($journal));
    store($root . '/storage/updates/maintenance.json', json_encode(['backup' => basename($backup)]));
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/bin/update-recover.php') . ' ' . escapeshellarg(basename($backup)) . ' 2>&1', $output, $status);
    verify($status === 0 && file_get_contents($root . '/core/state.php') === 'old-code', 'Aborted recovery failed: ' . implode("\n", $output));

    verify(!\Core\Database\SqlDialect::tableExists($db, 'update_core_fixture'), 'Recovery retained new DDL');
    verify(!\Core\Database\SqlDialect::tableExists($db, 'update_module_fixture'), 'Recovery retained new module DDL');
    echo "MYSQL UPDATE OK: snapshot, DDL failure rollback, commit marker, committed/crashed recovery\n";
} finally {
    DeploymentGuard::release($root);
    if ($db->inTransaction()) $db->rollBack();
    if (is_dir($base)) {
        $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($paths as $path) $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        rmdir($base);
    }
}
