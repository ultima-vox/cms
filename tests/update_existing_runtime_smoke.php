<?php

declare(strict_types=1);

// Run with the baseline autoloader and already-loaded baseline providers, just like FPM.
$root = $argv[1] ?? '';
$archive = $argv[2] ?? '';
if (getenv('DB_NAME') !== 'cms_upgrade_runtime' || !is_file($root . '/release.json') || !is_file($archive)) throw new RuntimeException('Disposable upgrade installation required.');
require $root . '/vendor/autoload.php';
$db = Core\Database::connection();
$core = new Core\Extension\Core(new Core\Extension\Api\RuntimeApi($db, $root));
(new Core\Bootstrap\BuiltinExtensions())->register($core);
(new Core\Extension\ModuleLoader($root))->load($core);
$core->freeze();
if (Core\Version::STRING !== '0.2.0') throw new RuntimeException('Baseline Core was not loaded before replacement.');
$zip = new ZipArchive(); $zip->open($archive); $manifest = json_decode($zip->getFromName('update.json'), true); $zip->close();
$bytes = file_get_contents($archive);
$backup = (new UltimaVox\Modules\Updater\FileUpdater($root, $db))->apply($bytes, ['version' => $manifest['version'], 'sha256' => hash('sha256', $bytes)]);
if ($db->query('SELECT 1 FROM extension_sync_jobs')->fetchColumn() === false) throw new RuntimeException('Fresh-worker reconciliation was not queued.');
if (!is_file($backup . '/journal.json')) throw new RuntimeException('Upgrade backup is absent.');
echo "Existing runtime update OK: old Core/providers/classmap remained loaded during code replacement and transactional migrations\n";
