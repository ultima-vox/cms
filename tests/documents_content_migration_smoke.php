<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\ModuleMigrationRunner;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$db = Database::connection();
$runner = new ModuleMigrationRunner($db, $root);

$siteStatement = $db->query(
    "INSERT INTO sites (code, name) VALUES ('documents-migration-fixture', 'Documents Migration Fixture') RETURNING id"
);
$siteId = (int) $siteStatement->fetchColumn();
if ($siteId < 1) {
    throw new RuntimeException('Documents migration fixture site was not created.');
}

try {
    $parentStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO nodes (
            site_id, parent_id, name, slug, path, title, content, status, page_type, page_config
        )
        VALUES (
            :site_id, NULL, 'Migration Root', '', '/', 'Migration Root', '', 'draft', 'fixture.parent', '{}'::jsonb
        )
        RETURNING id
        SQL
    );
    $parentStatement->execute(['site_id' => $siteId]);
    $parentId = (int) $parentStatement->fetchColumn();

    $legacyStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO nodes (
            site_id, parent_id, name, slug, path, title, content, status, page_type, page_config
        )
        VALUES (
            :site_id,
            :parent_id,
            'Legacy Page',
            'legacy-page',
            '/legacy-page',
            'Legacy Title',
            '<section>Legacy body</section>',
            'published',
            'core.content',
            '{}'::jsonb
        )
        RETURNING id
        SQL
    );
    $legacyStatement->execute([
        'site_id' => $siteId,
        'parent_id' => $parentId,
    ]);
    $legacyNodeId = (int) $legacyStatement->fetchColumn();

    $boundStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO nodes (
            site_id, parent_id, name, slug, path, title, content, status, page_type, page_config
        )
        VALUES (
            :site_id,
            :parent_id,
            'Bound Page',
            'bound-page',
            '/bound-page',
            'Bound Title',
            '<section>Bound body</section>',
            'published',
            'core.content',
            '{}'::jsonb
        )
        RETURNING id
        SQL
    );
    $boundStatement->execute([
        'site_id' => $siteId,
        'parent_id' => $parentId,
    ]);
    $boundNodeId = (int) $boundStatement->fetchColumn();

    $binding = $db->prepare(
        <<<'SQL'
        INSERT INTO node_module_bindings (node_id, module_code, binding_code, target_key)
        VALUES (:node_id, 'fixture', 'primary', 'fixture-target')
        SQL
    );
    $binding->execute(['node_id' => $boundNodeId]);

    $applied = $runner->migrate('documents');
    if (!in_array('002_migrate_node_content.sql', $applied, true)) {
        throw new RuntimeException('Legacy node content migration was not applied.');
    }

    $documentCode = 'node-' . $legacyNodeId;
    $document = $db->prepare(
        <<<'SQL'
        SELECT id, code, name
        FROM documents
        WHERE site_id = :site_id
          AND code = :code
        LIMIT 1
        SQL
    );
    $document->execute([
        'site_id' => $siteId,
        'code' => $documentCode,
    ]);
    $documentRow = $document->fetch(PDO::FETCH_ASSOC);
    if (!is_array($documentRow)
        || ($documentRow['code'] ?? null) !== $documentCode
        || ($documentRow['name'] ?? null) !== 'Legacy Title') {
        throw new RuntimeException('Migrated document identity is invalid.');
    }

    $documentId = (int) ($documentRow['id'] ?? 0);
    $version = $db->prepare(
        <<<'SQL'
        SELECT version, content, status
        FROM document_versions
        WHERE document_id = :document_id
        LIMIT 1
        SQL
    );
    $version->execute(['document_id' => $documentId]);
    $versionRow = $version->fetch(PDO::FETCH_ASSOC);
    if (!is_array($versionRow)
        || (int) ($versionRow['version'] ?? 0) !== 1
        || ($versionRow['content'] ?? null) !== '<section>Legacy body</section>'
        || ($versionRow['status'] ?? null) !== 'published') {
        throw new RuntimeException('Legacy node content was not copied into a published document version.');
    }

    $node = $db->prepare(
        'SELECT page_type, page_config, content FROM nodes WHERE id = :id'
    );
    $node->execute(['id' => $legacyNodeId]);
    $nodeRow = $node->fetch(PDO::FETCH_ASSOC);
    $pageConfig = is_array($nodeRow)
        ? json_decode((string) ($nodeRow['page_config'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR)
        : null;
    if (!is_array($nodeRow)
        || ($nodeRow['page_type'] ?? null) !== 'documents.page'
        || !is_array($pageConfig)
        || ($pageConfig['document'] ?? null) !== $documentCode
        || ($nodeRow['content'] ?? null) !== '<section>Legacy body</section>') {
        throw new RuntimeException('Migrated node page selection or rollback content bridge is invalid.');
    }

    $node->execute(['id' => $boundNodeId]);
    $boundRow = $node->fetch(PDO::FETCH_ASSOC);
    if (!is_array($boundRow)
        || ($boundRow['page_type'] ?? null) !== 'core.content'
        || ($boundRow['content'] ?? null) !== '<section>Bound body</section>') {
        throw new RuntimeException('Primary module-bound node was incorrectly migrated to Documents.');
    }

    $boundDocumentCount = $db->prepare(
        'SELECT COUNT(*) FROM documents WHERE site_id = :site_id AND code = :code'
    );
    $boundDocumentCount->execute([
        'site_id' => $siteId,
        'code' => 'node-' . $boundNodeId,
    ]);
    if ((int) $boundDocumentCount->fetchColumn() !== 0) {
        throw new RuntimeException('Documents migration created a document for a primary module-bound node.');
    }

    if ($runner->migrate('documents') !== []) {
        throw new RuntimeException('Applied Documents content migration was executed twice.');
    }
} finally {
    $deleteNodes = $db->prepare('DELETE FROM nodes WHERE site_id = :site_id');
    $deleteNodes->execute(['site_id' => $siteId]);

    $deleteSite = $db->prepare('DELETE FROM sites WHERE id = :site_id');
    $deleteSite->execute(['site_id' => $siteId]);
}

fwrite(STDOUT, "DOCUMENTS CONTENT MIGRATION OK\n");
