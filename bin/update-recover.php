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
$maintenance = $directory . '/maintenance.json';
$active = json_decode((string) @file_get_contents($maintenance), true);
if (($active['backup'] ?? null) !== $name) throw new RuntimeException('Recovery is only allowed for the interrupted active update.');
if (($journal['db_transaction'] ?? null) !== null) {
    // PostgreSQL 16+ retains the authoritative COMMIT outcome. Never guess it from files.
    $transaction = $journal['db_transaction'];
    if (!is_string($transaction) || !ctype_digit($transaction)) throw new RuntimeException('Invalid transaction ID.');
    require $root . '/vendor/autoload.php';
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
    $db = Core\Database::connection();
    $statement = $db->prepare('SELECT pg_xact_status(CAST(:transaction AS xid8))');
    $statement->execute(['transaction' => $transaction]);
    $status = $statement->fetchColumn();
    if ($status === 'committed') {
        $journal['status'] = 'complete';
        file_put_contents($backup . '/journal.json', json_encode($journal, JSON_THROW_ON_ERROR));
        if (!unlink($maintenance)) throw new RuntimeException('Cannot exit maintenance.');
        echo "Database committed: new files retained. Restart the PHP handler to clear its opcode cache.\n";
        exit;
    }
    if ($status !== 'aborted') throw new RuntimeException('Transaction outcome is not yet available; keep maintenance and retry.');
}
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
$journal['status'] = 'rolled_back';
file_put_contents($backup . '/journal.json', json_encode($journal, JSON_THROW_ON_ERROR));
if (is_file($maintenance) && !unlink($maintenance)) throw new RuntimeException('Cannot exit maintenance.');
echo "Files restored. Restart the PHP handler in FASTPANEL to clear its opcode cache. Database transaction was aborted (or no transaction was used).\n";
