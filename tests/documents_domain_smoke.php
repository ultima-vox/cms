<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\ModuleMigrationRunner;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/modules/documents/autoload.php';

$root = dirname(__DIR__);
$db = Database::connection();
$migrations = new ModuleMigrationRunner($db, $root);
$applied = $migrations->migrate('documents');
if ($applied !== ['001_documents.sql'] && $applied !== []) {
    throw new RuntimeException('Unexpected documents module migration result.');
}

$repository = new DocumentRepository($db);
$db->beginTransaction();

try {
    $documentId = $repository->create(1, 'footer-contacts', 'Footer contacts');
    $document = $repository->findActiveByCode(1, 'FOOTER-CONTACTS');
    if ($document === null
        || (int) ($document['id'] ?? 0) !== $documentId
        || ($document['code'] ?? null) !== 'footer-contacts') {
        throw new RuntimeException('Document lookup by stable code failed.');
    }

    $firstVersionId = $repository->createDraftVersion($documentId, '<p>Version 1</p>');
    $repository->publishVersion($documentId, $firstVersionId);

    $published = $repository->currentPublishedVersion($documentId);
    if ($published === null
        || (int) ($published['id'] ?? 0) !== $firstVersionId
        || (int) ($published['version'] ?? 0) !== 1
        || ($published['content'] ?? null) !== '<p>Version 1</p>') {
        throw new RuntimeException('First document version was not published correctly.');
    }

    $secondVersionId = $repository->createDraftVersion($documentId, '<p>Version 2</p>');
    $repository->publishVersion($documentId, $secondVersionId);

    $published = $repository->currentPublishedVersion($documentId);
    if ($published === null
        || (int) ($published['id'] ?? 0) !== $secondVersionId
        || (int) ($published['version'] ?? 0) !== 2
        || ($published['content'] ?? null) !== '<p>Version 2</p>') {
        throw new RuntimeException('Current published document version did not advance.');
    }

    $firstStatus = $db->prepare('SELECT status FROM document_versions WHERE id = :id');
    $firstStatus->execute(['id' => $firstVersionId]);
    if ($firstStatus->fetchColumn() !== 'archived') {
        throw new RuntimeException('Previous published document version was not archived.');
    }

    try {
        $repository->create(1, 'footer-contacts', 'Duplicate');
        throw new RuntimeException('Duplicate site-scoped document code was accepted.');
    } catch (Throwable $exception) {
        if ($exception->getMessage() === 'Duplicate site-scoped document code was accepted.') {
            throw $exception;
        }
    }

    if ($repository->findActiveByCode(1, 'missing-document') !== null) {
        throw new RuntimeException('Missing document lookup returned a record.');
    }
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

fwrite(STDOUT, "DOCUMENTS DOMAIN OK\n");
