<?php

declare(strict_types=1);

// Test-only instrumentation. No SQL text, credentials, sessions or response body are emitted.
require dirname(__DIR__) . '/vendor/autoload.php';
if (getenv('APP_ENV') !== 'testing' || getenv('G0_DISPOSABLE') !== '1' || getenv('DB_NAME') !== 'cms_g0_perf') {
    throw new RuntimeException('Dedicated disposable G0 database required.');
}
final class G0SqlCounter
{
    public static int $executions = 0;
}
final class G0Statement extends PDOStatement
{
    protected function __construct() {}

    public function execute(?array $params = null): bool
    {
        G0SqlCounter::$executions++;
        return parent::execute($params);
    }
}
final class G0Pdo extends PDO
{
    public function exec(string $statement): int|false
    {
        G0SqlCounter::$executions++;
        return parent::exec($statement);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        G0SqlCounter::$executions++;
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
final class G0Cache implements Core\Delivery\Cache\CacheBackendInterface
{
    public int $pageHits = 0;
    public int $pageMisses = 0;

    public function __construct(private Core\Delivery\Cache\CacheBackendInterface $inner) {}

    public function get(string $key): ?string
    {
        $value = $this->inner->get($key);
        if (str_starts_with($key, 'page:')) {
            if ($value === null) $this->pageMisses++; else $this->pageHits++;
        }
        return $value;
    }

    public function put(string $key, string $value, array $tags = [], ?int $ttlSeconds = null): void
    {
        $this->inner->put($key, $value, $tags, $ttlSeconds);
    }

    public function delete(string $key): void { $this->inner->delete($key); }
    public function invalidate(array $tags): int { return $this->inner->invalidate($tags); }
}
$directory = $argv[1] ?? throw new RuntimeException('Fixture directory required.');
$scenario = json_decode($argv[2] ?? '', true, flags: JSON_THROW_ON_ERROR);
$fixture = json_decode((string) file_get_contents($directory . '/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$driver = Core\Config::string('DB_DRIVER', 'pgsql');
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => $driver === 'mysql', PDO::ATTR_STATEMENT_CLASS => [G0Statement::class]];
if ($driver === 'mysql') $options[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
$db = new G0Pdo(sprintf('%s:host=%s;port=%s;dbname=%s%s', $driver, Core\Config::string('DB_HOST'), Core\Config::string('DB_PORT'), Core\Config::string('DB_NAME'), $driver === 'mysql' ? ';charset=utf8mb4' : ''), Core\Config::string('DB_USER'), Core\Config::string('DB_PASS'), $options);
if ($driver === 'mysql') $db->exec("SET time_zone = '+00:00'");
// Verify direct, exec, prepared repeated and parameterized paths before each sample.
G0SqlCounter::$executions = 0;
$db->query('SELECT 1')->fetchColumn();
$db->exec($driver === 'pgsql' ? 'DO $$BEGIN NULL; END$$;' : 'DO 0');
$statement = $db->prepare('SELECT :value');
$statement->execute(['value' => 2]); $statement->fetchColumn();
$statement->execute(['value' => 3]); $statement->fetchColumn();
if (G0SqlCounter::$executions !== 4) throw new RuntimeException('SQL instrumentation count failed.');
unset($statement);
$serverVersion = (string) $db->getAttribute(PDO::ATTR_SERVER_VERSION);
$isolation = (string) $db->query($driver === 'pgsql' ? 'SHOW transaction_isolation' : (str_contains($serverVersion, 'MariaDB') ? 'SELECT @@tx_isolation' : 'SELECT @@transaction_isolation'))->fetchColumn();
// Set the existing private connection only in this disposable worker; no production hook added.
(new ReflectionProperty(Core\Database::class, 'instance'))->setValue(null, $db);
$site = $fixture['sites'][$scenario['site'] ?? 'primary'];
$_SERVER['HTTP_HOST'] = $site['host'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$uri = $scenario['uri'];
$path = (string) parse_url($uri, PHP_URL_PATH);
$query = [];
parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
if (str_starts_with($path, '/admin')) {
    session_save_path($directory . '/sessions');
    session_id($fixture['session']);
    session_start();
}
$cache = new G0Cache(new Core\Delivery\Cache\TaggedCache(
    new Core\Delivery\Cache\FilesystemCacheStore($directory . '/cache/data'),
    new Core\Delivery\Cache\FilesystemTagIndex($directory . '/cache/index'),
));
G0SqlCounter::$executions = 0;
memory_reset_peak_usage();
$before = memory_get_usage(true);
ob_start();
$started = hrtime(true);
(new Core\Application(dirname(__DIR__), $cache))->run(new Core\Http\Request('GET', $uri, $path, $query, [], $_SERVER, []));
$elapsed = (hrtime(true) - $started) / 1e6;
$body = (string) ob_get_clean();
$peak = memory_get_peak_usage(true);
$status = http_response_code();
if ($status !== ($scenario['status'] ?? 200)) throw new RuntimeException('Unexpected dispatch status ' . $status);
foreach ($scenario['contains'] ?? [] as $text) {
    if (!str_contains($body, $text)) throw new RuntimeException('Expected fixture marker missing: ' . $text);
}
foreach ($scenario['excludes'] ?? [] as $text) {
    if (str_contains($body, $text)) throw new RuntimeException('Forbidden fixture content present: ' . $text);
}
if (isset($scenario['pageCache']) && ($scenario['pageCache'] === 'hit' ? $cache->pageHits < 1 : $cache->pageMisses < 1)) {
    throw new RuntimeException('Expected page-cache state not observed.');
}
echo json_encode([
    'dispatch_ms' => $elapsed, 'sql_executions' => G0SqlCounter::$executions,
    'peak_allocated_bytes' => $peak, 'peak_delta_bytes' => max(0, $peak - $before),
    'body_bytes' => strlen($body), 'body_sha256' => hash('sha256', $body),
    'page_hits' => $cache->pageHits, 'page_misses' => $cache->pageMisses, 'status' => $status,
    'environment' => ['php' => PHP_VERSION, 'sapi' => PHP_SAPI, 'db_server' => $serverVersion, 'transaction_isolation' => $isolation, 'pdo_driver' => $driver, 'memory_limit' => ini_get('memory_limit'), 'opcache_cli' => ini_get('opcache.enable_cli'), 'jit' => ini_get('opcache.jit')],
], JSON_THROW_ON_ERROR) . "\n";
