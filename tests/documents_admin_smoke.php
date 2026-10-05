<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\ModuleMigrationRunner;
use UltimaVox\Modules\Documents\Admin\DocumentAdminService;
use UltimaVox\Modules\Documents\Repository\DocumentManagementRepository;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

$rootPath = dirname(__DIR__);
require_once $rootPath . '/vendor/autoload.php';
require_once $rootPath . '/modules/documents/autoload.php';

$db = Database::connection();
(new ModuleMigrationRunner($db, $rootPath))->migrate('documents');

$siteStatement = $db->query(
    "INSERT INTO sites (code, name) VALUES ('documents-admin-smoke', 'Documents Admin Smoke') RETURNING id"
);
$siteId = (int) $siteStatement->fetchColumn();
if ($siteId < 1) {
    throw new RuntimeException('Documents admin smoke site was not created.');
}

try {
    $repository = new DocumentRepository($db);
    $management = new DocumentManagementRepository($db, $siteId);
    $service = new DocumentAdminService($db, $siteId, $repository, $management);

    $documentId = $service->create(
        'company-requisites',
        'Company requisites',
        '<p>Draft one</p>',
        false,
    );

    $document = $management->find($documentId);
    if (!is_array($document)
        || ($document['code'] ?? null) !== 'company-requisites'
        || ($document['name'] ?? null) !== 'Company requisites') {
        throw new RuntimeException('Documents admin service created an unexpected document.');
    }

    $versions = $management->versions($documentId);
    if (count($versions) !== 1
        || ($versions[0]['status'] ?? null) !== 'draft'
        || ($versions[0]['content'] ?? null) !== '<p>Draft one</p>') {
        throw new RuntimeException('Documents admin service did not create the initial draft version.');
    }

    $publishedVersionId = $service->save(
        $documentId,
        'Company details',
        '<p>Published two</p>',
        true,
    );
    $published = $repository->currentPublishedVersion($documentId);
    if (!is_array($published)
        || (int) ($published['id'] ?? 0) !== $publishedVersionId
        || (int) ($published['version'] ?? 0) !== 2
        || ($published['content'] ?? null) !== '<p>Published two</p>') {
        throw new RuntimeException('Documents admin service did not publish the second version.');
    }

    $versions = $management->versions($documentId);
    if (count($versions) !== 2
        || ($versions[0]['status'] ?? null) !== 'published'
        || ($versions[1]['status'] ?? null) !== 'archived') {
        throw new RuntimeException('Publishing did not archive stale draft versions.');
    }

    $editable = $management->editableVersion($documentId);
    if (!is_array($editable)
        || (int) ($editable['id'] ?? 0) !== $publishedVersionId
        || ($editable['content'] ?? null) !== '<p>Published two</p>') {
        throw new RuntimeException('Documents editor did not use the newest published version after publish.');
    }

    $document = $management->find($documentId);
    if (!is_array($document)
        || ($document['code'] ?? null) !== 'company-requisites'
        || ($document['name'] ?? null) !== 'Company details') {
        throw new RuntimeException('Documents admin service changed stable code or failed to update name.');
    }

    $draftVersionId = $service->save(
        $documentId,
        'Company details',
        '<p>Draft three</p>',
        false,
    );
    $editable = $management->editableVersion($documentId);
    if (!is_array($editable)
        || (int) ($editable['id'] ?? 0) !== $draftVersionId
        || (int) ($editable['version'] ?? 0) !== 3
        || ($editable['status'] ?? null) !== 'draft'
        || ($editable['content'] ?? null) !== '<p>Draft three</p>') {
        throw new RuntimeException('Documents admin editor did not prefer the newest version.');
    }

    $versions = $management->versions($documentId);
    if (count($versions) !== 3
        || (int) ($versions[0]['version'] ?? 0) !== 3
        || (int) ($versions[1]['version'] ?? 0) !== 2
        || (int) ($versions[2]['version'] ?? 0) !== 1) {
        throw new RuntimeException('Documents version history ordering is invalid.');
    }

    $otherSiteStatement = $db->query(
        "INSERT INTO sites (code, name) VALUES ('documents-admin-other', 'Documents Admin Other') RETURNING id"
    );
    $otherSiteId = (int) $otherSiteStatement->fetchColumn();
    $otherManagement = new DocumentManagementRepository($db, $otherSiteId);
    if ($otherManagement->find($documentId) !== null || $otherManagement->all() !== []) {
        throw new RuntimeException('Documents admin repository violated site isolation.');
    }
} finally {
    $deleteSites = $db->prepare(
        "DELETE FROM sites WHERE code IN ('documents-admin-smoke', 'documents-admin-other')"
    );
    $deleteSites->execute();
}

fwrite(STDOUT, "DOCUMENTS ADMIN OK\n");
