<?php

declare(strict_types=1);

use Core\Database;
use Core\Database\MysqlMigrationRunner;
use Core\Database\MysqlSnapshot;
use Core\Database\SqlDialect;

require dirname(__DIR__) . '/vendor/autoload.php';
if (getenv('DB_NAME') !== 'cms_legacy') throw new RuntimeException('Disposable legacy database required.');
$db = Database::connection();
$path = sys_get_temp_dir() . '/mysql-migration-failure-' . bin2hex(random_bytes(5));
mkdir($path . '/mysql', 0700, true);
$name = '998_failure_fixture.sql';
file_put_contents($path . '/mysql/' . $name, 'CREATE TABLE mysql_failed_fixture(id INTEGER) ENGINE=InnoDB; SELECT missing_legacy_migration_function();');
try {
    $failed = false;
    try { (new MysqlMigrationRunner($db))->migrate($path); } catch (Throwable) { $failed = true; }
    if (!$failed || !SqlDialect::tableExists($db, 'mysql_failed_fixture')) throw new RuntimeException('MySQL nontransactional DDL failure was not exercised.');
    $q = $db->prepare('SELECT dirty FROM schema_migrations WHERE migration=:name'); $q->execute(['name' => $name]);
    if (!(bool) $q->fetchColumn()) throw new RuntimeException('Failed migration lost its durable dirty marker.');
    $blocked = false;
    try { (new MysqlMigrationRunner($db))->migrate($path); } catch (RuntimeException $error) { $blocked = str_contains($error->getMessage(), 'Незавершённая'); }
    if (!$blocked) throw new RuntimeException('Dirty migration was automatically retried.');
    $incomplete = $path . '/incomplete.jsonl';
    file_put_contents($incomplete, "{\"format\":1}\n");
    $rejected = false;
    try { MysqlSnapshot::restore($db, $incomplete, hash_file('sha256', $incomplete)); } catch (RuntimeException) { $rejected = true; }
    if (!$rejected || !SqlDialect::tableExists($db, 'sites')) throw new RuntimeException('Incomplete snapshot touched the database.');
    echo "MYSQL FAILURE OK: implicit DDL commit, durable dirty marker, retry denied, incomplete snapshot rejected\n";
} finally {
    $db->exec('DROP TABLE IF EXISTS mysql_failed_fixture');
    $q = $db->prepare('DELETE FROM schema_migrations WHERE migration=:name'); $q->execute(['name' => $name]);
    foreach (glob($path . '/mysql/*') ?: [] as $file) unlink($file);
    foreach (glob($path . '/*.jsonl') ?: [] as $file) unlink($file);
    rmdir($path . '/mysql'); rmdir($path);
}
