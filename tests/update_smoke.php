<?php

declare(strict_types=1);

require dirname(__DIR__) . '/modules/updater/autoload.php';
use UltimaVox\Modules\Updater\FileUpdater;

function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function put(string $path, string $content): void { if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true); file_put_contents($path, $content); }
function bundle(array $files, string $version = '0.1.2', array $extra = []): array {
    $hashes = array_map(static fn ($v) => hash('sha256', $v), $files);
    $schema = array_filter($hashes, static fn ($k) => str_ends_with($k, '.sql'), ARRAY_FILTER_USE_KEY);
    $file = tempnam(sys_get_temp_dir(), 'update-zip-');
    $zip = new ZipArchive(); $zip->open($file, ZipArchive::OVERWRITE);
    $zip->addFromString('update.json', json_encode(['version' => $version, 'files' => $hashes, 'schema' => $schema], JSON_THROW_ON_ERROR));
    foreach ($files as $name => $content) $zip->addFromString('payload/' . $name, $content);
    foreach ($extra as $name => $content) $zip->addFromString($name, $content);
    $zip->close(); $bytes = file_get_contents($file); unlink($file);
    return [$bytes, ['version' => $version, 'sha256' => hash('sha256', $bytes)]];
}
function reject(callable $fn, string $message): void { try { $fn(); } catch (Throwable) { return; } throw new RuntimeException($message); }
$base = sys_get_temp_dir() . '/cms-update-test-' . bin2hex(random_bytes(6));
foreach (['public', 'webroot'] as $layout) {
    $directory = $base . '/' . $layout;
    $root = $layout === 'webroot' ? $directory . '/app' : $directory;
    $old = ['core/state.php' => 'old-code', 'core/obsolete.php' => 'obsolete', 'public/assets/site.css' => 'old-css', 'database/migrations/001.sql' => 'schema'];
    foreach ($old as $name => $content) put($root . '/' . $name, $content);
    if ($layout === 'webroot') put($directory . '/assets/site.css', 'old-css');
    put($root . '/.env', 'keep-secret'); put($root . '/storage/templates/layouts/custom.html.php', 'keep-template');
    put($root . '/storage/cache/custom', 'keep-cache'); put($root . '/modules/custom/local.php', 'keep-module');
    $hashes = array_map(static fn ($v) => hash('sha256', $v), $old);
    put($root . '/release.json', json_encode(['version' => '0.1.1', 'layout' => $layout, 'files' => $hashes, 'schema' => ['database/migrations/001.sql' => hash('sha256', 'schema')]]));
    $updater = new FileUpdater($root);
    $new = ['core/state.php' => 'new-code', 'public/assets/site.css' => 'new-css', 'database/migrations/001.sql' => 'schema', 'modules/mechaniki/site/activate.php' => '<?php echo "site package";'];
    [$bytes, $offer] = bundle($new);
    reject(fn () => $updater->apply($bytes, array_replace($offer, ['sha256' => str_repeat('0', 64)])), 'Invalid hash accepted');
    [$bad, $badOffer] = bundle($new, extra: ['../outside' => 'bad']);
    reject(fn () => $updater->apply($bad, $badOffer), 'Extra path accepted');
    [$bad, $badOffer] = bundle($new + ['../outside' => 'bad']);
    reject(fn () => $updater->apply($bad, $badOffer), 'Traversal accepted');
    [$bad, $badOffer] = bundle($new + ['core/new.sql' => 'schema-change']);
    reject(fn () => $updater->apply($bad, $badOffer), 'Changed DB schema accepted');
    put($root . '/core/state.php', 'local-change');
    reject(fn () => $updater->apply($bytes, $offer), 'Local edit overwritten');
    check(file_get_contents($root . '/core/state.php') === 'local-change', 'Local edit lost');
    put($root . '/core/state.php', 'old-code');
    $held = fopen($root . '/storage/updates/requests.lock', 'c'); flock($held, LOCK_SH);
    reject(fn () => $updater->apply($bytes, $offer), 'Active requests ignored');
    flock($held, LOCK_UN); fclose($held);
    // A parent occupied by a file forces a write failure after the first file replacement.
    put($root . '/public/assets/occupied', 'file');
    [$bad, $badOffer] = bundle($new + ['public/assets/occupied/child.css' => 'new']);
    reject(fn () => $updater->apply($bad, $badOffer), 'Write failure accepted');
    check(file_get_contents($root . '/core/state.php') === 'old-code', 'Code rollback failed');
    check(!is_file($root . '/storage/updates/maintenance.json'), 'Rollback left maintenance mode');
    [$forbiddenSite, $forbiddenOffer] = bundle($new + ['sites/private/config.php' => 'forbidden']);
    reject(fn () => $updater->apply($forbiddenSite, $forbiddenOffer), 'Non-allowlisted site package accepted');
    [$secretSite, $secretOffer] = bundle($new + ['sites/mechaniki/.env' => 'secrets']);
    reject(fn () => $updater->apply($secretSite, $secretOffer), 'Site .env accepted');
    $backup = $updater->apply($bytes, $offer);
    check(is_file($backup . '/journal.json'), 'Backup absent');
    check(file_get_contents($root . '/core/state.php') === 'new-code', 'Code did not update');
    check(is_file($root . '/modules/mechaniki/site/activate.php'), 'Packaged site activation was not installed');
    check(!file_exists($root . '/core/obsolete.php'), 'Obsolete file remains');
    check(file_get_contents($root . '/public/assets/site.css') === 'new-css', 'CSS did not update');
    if ($layout === 'webroot') check(file_get_contents($directory . '/assets/site.css') === 'new-css', 'Webroot CSS mirror stale');
    foreach (['.env' => 'keep-secret', 'storage/templates/layouts/custom.html.php' => 'keep-template', 'storage/cache/custom' => 'keep-cache', 'modules/custom/local.php' => 'keep-module'] as $path => $content) check(file_get_contents($root . '/' . $path) === $content, 'User data overwritten');
    check($updater->current()['version'] === '0.1.2', 'Version not saved');
    reject(fn () => $updater->apply($bytes, $offer), 'Reinstall accepted');
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $path) { $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname()); }
rmdir($base);
echo "Update engine OK: both layouts, checksums, paths, schema guard, local edits, locking, backup, rollback and preserved data\n";
