<?php

declare(strict_types=1);

require dirname(__DIR__) . '/modules/updater/autoload.php';
use UltimaVox\Modules\Updater\FileUpdater;
use UltimaVox\Modules\Updater\ReleaseClient;

$client = new ReleaseClient();
$offer = $client->latest();
$directory = sys_get_temp_dir() . '/cms-live-update-' . bin2hex(random_bytes(6));
mkdir($directory, 0755, true);
$zipPath = $directory . '/distribution.zip';
$distribution = preg_replace('~/cms-update\.zip$~', '/ultima-vox-cms-mvp-webroot.zip', $offer['url']);
file_put_contents($zipPath, $client->download($distribution, 80_000_000));
$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) throw new RuntimeException('Distribution could not be opened.');
$zip->extractTo($directory); $zip->close(); unlink($zipPath);
$root = $directory . '/app';
$current = json_decode(file_get_contents($root . '/release.json'), true, 32, JSON_THROW_ON_ERROR);
$current['version'] = '0.0.0';
$name = 'public/assets/modules.css';
$current['files'][$name] = hash('sha256', 'older-css');
file_put_contents($root . '/' . $name, 'older-css');
file_put_contents($directory . '/assets/modules.css', 'older-css');
file_put_contents($root . '/release.json', json_encode($current, JSON_THROW_ON_ERROR));
file_put_contents($root . '/.env', 'PRESERVE_THIS_CONFIG');
$updater = new FileUpdater($root);
$backup = $updater->apply($client->download($offer['url'], 80_000_000), $offer);
if ($updater->current()['version'] !== $offer['version']
    || file_get_contents($root . '/' . $name) === 'older-css'
    || file_get_contents($root . '/' . $name) !== file_get_contents($directory . '/assets/modules.css')
    || file_get_contents($root . '/.env') !== 'PRESERVE_THIS_CONFIG'
    || !is_file($backup . '/journal.json')) {
    throw new RuntimeException('Live release update did not preserve or replace expected files.');
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $path) $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
rmdir($directory);
echo 'Live online update OK: official feed -> HTTPS downloads -> verified archive -> CSS replacement -> preserved .env -> ' . $offer['version'] . "\n";
