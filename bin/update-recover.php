<?php

declare(strict_types=1);

// Standalone recovery: deliberately does not load the possibly interrupted application.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$directory = $root . '/storage/updates';
$name = $argv[1] ?? '';
if (!preg_match('/^backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$/D', $name)) {
    fwrite(STDERR, "Usage: php bin/update-recover.php backup-YYYYMMDD-HHMMSS-xxxxxxxx\n"); exit(1);
}
$lock = fopen($directory . '/apply.lock', 'c');
$requests = fopen($directory . '/requests.lock', 'c');
if (!$lock || !$requests || !flock($lock, LOCK_EX | LOCK_NB) || !flock($requests, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Update or site requests still active. Retry.\n"); exit(1);
}
$backup = $directory . '/' . $name;
$journal = json_decode((string) file_get_contents($backup . '/journal.json'), true, 32, JSON_THROW_ON_ERROR);
foreach (array_reverse($journal['files']) as $entry) {
    $target = $entry['target'];
    if (!str_starts_with($target, dirname($root) . '/') || str_contains($target, '/../')) throw new RuntimeException('Invalid recovery path.');
    if ($entry['backup'] === null) {
        if (is_file($target) && !unlink($target)) throw new RuntimeException('Cannot remove new file.');
    } else {
        $source = $entry['backup'];
        if (!preg_match('/^[0-9]+\.bak$/D', $source)) throw new RuntimeException('Invalid backup path.');
        $temporary = $target . '.recover-' . bin2hex(random_bytes(4));
        if (!copy($backup . '/' . $source, $temporary) || !chmod($temporary, $entry['mode']) || !rename($temporary, $target)) throw new RuntimeException('Cannot restore file.');
    }
}
if (is_file($directory . '/maintenance.json')) unlink($directory . '/maintenance.json');
echo "Files restored. Restart the PHP handler in FASTPANEL to clear its opcode cache. Database was not modified.\n";
