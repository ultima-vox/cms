<?php

declare(strict_types=1);

use Core\Database;
use Core\Database\SqlDialect;
use UltimaVox\Modules\Documents\Admin\DocumentAdminService;
use UltimaVox\Modules\Documents\Admin\DocumentConflictException;
use UltimaVox\Modules\Documents\Repository\DocumentManagementRepository;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/modules/documents/autoload.php';
$db = Database::connection();
$repo = new DocumentRepository($db);
if (($argv[1] ?? '') === '--worker') {
    [$document, $site, $revision, $author] = array_map('intval', array_slice($argv, 2));
    $service = new DocumentAdminService($db, $site, $repo, new DocumentManagementRepository($db, $site));
    echo "READY\n";
    flush();
    try {
        $service->save($document, 'Winner ' . $author, '<p>Writer ' . $author . '</p>', true, null, $revision, $author);
        echo "SAVED\n";
    } catch (DocumentConflictException) {
        echo "CONFLICT\n";
    }
    exit;
}
$assert = static function (bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); };
$insert = static function (string $sql, array $params) use ($db): int {
    $q = $db->prepare($sql . SqlDialect::returningId($db)); $q->execute($params);
    return SqlDialect::insertedId($db, $q);
};
$site = null; $other = null; $users = []; $children = [];
try {
    $site = $insert('INSERT INTO sites(code,name) VALUES(:code,:name)', ['code' => 'revision-fixture', 'name' => 'Revision fixture']);
    $other = $insert('INSERT INTO sites(code,name) VALUES(:code,:name)', ['code' => 'revision-other', 'name' => 'Other site']);
    foreach ([1, 2] as $i) {
        $users[] = $insert('INSERT INTO users(email,password_hash,display_name) VALUES(:email,:hash,:name)',
            ['email' => 'revision-' . $i . '@example.test', 'hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'name' => 'Writer ' . $i]);
    }
    $management = new DocumentManagementRepository($db, $site);
    $service = new DocumentAdminService($db, $site, $repo, $management);
    $document = $service->create('revision', 'Original', '<p>Legacy/system content</p>', true);
    $snapshot = $management->find($document);
    $revision = (int) $snapshot['expected_revision'];
    $assert($management->versions($document)[0]['author_user_id'] === null, 'Unknown author was invented');
    // Hold the same document lock until both independent PHP/DB workers are ready to save.
    $db->beginTransaction();
    $repo->assertCurrentRevision($document, $site, $revision);
    foreach ($users as $user) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $document, (string) $site, (string) $revision, (string) $user],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (!is_resource($process)) throw new RuntimeException('Unable to launch concurrent writer');
        stream_set_timeout($pipes[1], 20);
        $children[] = [$process, $pipes];
        $assert(trim((string) fgets($pipes[1])) === 'READY', 'Concurrent writer failed to connect');
    }
    $db->commit();
    $outcomes = [];
    foreach ($children as [$process, $pipes]) {
        $outcomes[] = trim(stream_get_contents($pipes[1]));
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $assert(proc_close($process) === 0, 'Concurrent writer failed: ' . $error);
    }
    $children = [];
    sort($outcomes);
    $assert($outcomes === ['CONFLICT', 'SAVED'], 'Concurrent writers did not yield one winner: ' . json_encode($outcomes));
    $versions = $management->versions($document);
    $assert(count($versions) === 2 && $versions[0]['status'] === 'published', 'Conflict mutated history/publication');
    $winner = (int) $versions[0]['author_user_id'];
    $assert(in_array($winner, $users, true) && $management->find($document)['name'] === 'Winner ' . $winner, 'Author and metadata diverged');
    $assert($repo->currentPublishedVersion($document)['content'] === '<p>Writer ' . $winner . '</p>', 'Published content diverged');
    foreach ([null, -1, $revision] as $stale) {
        try { $service->save($document, 'Overwrite', 'BAD', false, null, $stale, $users[0]); }
        catch (DocumentConflictException) { continue; }
        throw new RuntimeException('Missing/invalid/stale revision accepted');
    }
    $assert(count($management->versions($document)) === 2, 'Rejected save created a version');
    $foreign = new DocumentAdminService($db, $other, $repo, new DocumentManagementRepository($db, $other));
    $failed = false;
    try { $foreign->save($document, 'Foreign', 'BAD', true, null, (int) $versions[0]['id'], $users[0]); }
    catch (RuntimeException) { $failed = true; }
    $assert($failed, 'Foreign site modified a document');
    $empty = $repo->create($site, 'no-versions', 'Empty document');
    $service->save($empty, 'First version', 'First', false, null, 0, $users[0]);
    $assert(count($management->versions($empty)) === 1, 'Provisioned versionless document cannot be saved');
    $db->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $winner]);
    $assert($management->versions($document)[0]['author_user_id'] === null, 'Author deletion damaged history');
    $assert((new Core\Extension\ModuleMigrationRunner($db, dirname(__DIR__)))->migrate('documents') === [], 'Author migration was not repeatable');
    echo "DOCUMENT REVISION OK: two independent concurrent authors, single winner, locking reads, stale/missing/foreign rejection, history/publication atomicity, unknown/deleted author, versionless documents\n";
} finally {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($children as [$process, $pipes]) { if (is_resource($process)) proc_terminate($process); foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe); if (is_resource($process)) proc_close($process); }
    foreach ([$site, $other] as $id) if ($id !== null) $db->prepare('DELETE FROM sites WHERE id=:id')->execute(['id' => $id]);
    foreach ($users as $id) $db->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $id]);
}
