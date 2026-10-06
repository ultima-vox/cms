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

    $infosystemStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO infosystems (site_id, name, code, description, field_schema)
        VALUES (:site_id, 'Intro Catalog', 'intro-catalog', 'Migration fixture', '[]'::jsonb)
        RETURNING id
        SQL
    );
    $infosystemStatement->execute(['site_id' => $siteId]);
    $infosystemId = (int) $infosystemStatement->fetchColumn();

    $introNodeStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO nodes (
            site_id, parent_id, name, slug, path, title, content, status, page_type, page_config
        )
        VALUES (
            :site_id,
            :parent_id,
            'Infosystem Intro',
            'infosystem-intro',
            '/infosystem-intro',
            'Infosystem Intro',
            '<section>Intro body</section>',
            'published',
            'infosystem.list',
            '{"limit":10}'::jsonb
        )
        RETURNING id
        SQL
    );
    $introNodeStatement->execute([
        'site_id' => $siteId,
        'parent_id' => $parentId,
    ]);
    $introNodeId = (int) $introNodeStatement->fetchColumn();

    $infosystemBinding = $db->prepare(
        <<<'SQL'
        INSERT INTO node_module_bindings (node_id, module_code, binding_code, target_key)
        VALUES (:node_id, 'infosystem', 'primary', 'intro-catalog')
        SQL
    );
    $infosystemBinding->execute(['node_id' => $introNodeId]);

    $disabledIntroNodeStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO nodes (
            site_id, parent_id, name, slug, path, title, content, status, page_type, page_config
        )
        VALUES (
            :site_id,
            :parent_id,
            'Disabled Infosystem Intro',
            'disabled-infosystem-intro',
            '/disabled-infosystem-intro',
            'Disabled Infosystem Intro',
            '<section>Must stay legacy-only</section>',
            'published',
            'infosystem.list',
            '{"include_content":false}'::jsonb
        )
        RETURNING id
        SQL
    );
    $disabledIntroNodeStatement->execute([
        'site_id' => $siteId,
        'parent_id' => $parentId,
    ]);
    $disabledIntroNodeId = (int) $disabledIntroNodeStatement->fetchColumn();
    $infosystemBinding->execute(['node_id' => $disabledIntroNodeId]);

    $applied = $runner->migrate('documents');
    if (!in_array('002_migrate_node_content.sql', $applied, true)
        || !in_array('003_migrate_remaining_core_content.sql', $applied, true)
        || !in_array('004_migrate_infosystem_intro_content.sql', $applied, true)) {
        throw new RuntimeException('Legacy node content migrations were not applied.');
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
    $boundPageConfig = is_array($boundRow)
        ? json_decode((string) ($boundRow['page_config'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR)
        : null;
    if (!is_array($boundRow)
        || ($boundRow['page_type'] ?? null) !== 'documents.page'
        || !is_array($boundPageConfig)
        || ($boundPageConfig['document'] ?? null) !== 'node-' . $boundNodeId
        || ($boundRow['content'] ?? null) !== '<section>Bound body</section>') {
        throw new RuntimeException('Primary module-bound legacy node was not migrated to Documents.');
    }

    $boundDocumentCount = $db->prepare(
        'SELECT COUNT(*) FROM documents WHERE site_id = :site_id AND code = :code'
    );
    $boundDocumentCount->execute([
        'site_id' => $siteId,
        'code' => 'node-' . $boundNodeId,
    ]);
    if ((int) $boundDocumentCount->fetchColumn() !== 1) {
        throw new RuntimeException('Documents migration did not create the bound legacy document.');
    }

    $introNode = $db->prepare(
        'SELECT page_type, page_config, content FROM nodes WHERE id = :id'
    );
    $introNode->execute(['id' => $introNodeId]);
    $introNodeRow = $introNode->fetch(PDO::FETCH_ASSOC);
    $introPageConfig = is_array($introNodeRow)
        ? json_decode((string) ($introNodeRow['page_config'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR)
        : null;
    if (!is_array($introNodeRow)
        || ($introNodeRow['page_type'] ?? null) !== 'infosystem.list'
        || !is_array($introPageConfig)
        || ($introPageConfig['limit'] ?? null) !== 10
        || ($introPageConfig['include_content'] ?? null) !== false
        || ($introNodeRow['content'] ?? null) !== '<section>Intro body</section>') {
        throw new RuntimeException('Infosystem intro node configuration was not migrated safely.');
    }

    $introBinding = $db->prepare(
        <<<'SQL'
        SELECT target_key
        FROM node_module_bindings
        WHERE node_id = :node_id
          AND module_code = 'documents'
          AND binding_code = 'intro'
        LIMIT 1
        SQL
    );
    $introBinding->execute(['node_id' => $introNodeId]);
    $introDocumentCode = $introBinding->fetchColumn();
    if ($introDocumentCode !== 'node-' . $introNodeId . '-intro') {
        throw new RuntimeException('Infosystem intro document binding is invalid.');
    }

    $introDocument = $db->prepare(
        <<<'SQL'
        SELECT d.id, v.content, v.status
        FROM documents d
        JOIN document_versions v ON v.document_id = d.id
        WHERE d.site_id = :site_id
          AND d.code = :code
        LIMIT 1
        SQL
    );
    $introDocument->execute([
        'site_id' => $siteId,
        'code' => $introDocumentCode,
    ]);
    $introDocumentRow = $introDocument->fetch(PDO::FETCH_ASSOC);
    if (!is_array($introDocumentRow)
        || ($introDocumentRow['content'] ?? null) !== '<section>Intro body</section>'
        || ($introDocumentRow['status'] ?? null) !== 'published') {
        throw new RuntimeException('Infosystem intro content was not copied to a published document.');
    }

    $introBinding->execute(['node_id' => $disabledIntroNodeId]);
    if ($introBinding->fetchColumn() !== false) {
        throw new RuntimeException('Disabled Infosystem intro content was unexpectedly migrated.');
    }

    $disabledNode = $db->prepare('SELECT page_config FROM nodes WHERE id = :id');
    $disabledNode->execute(['id' => $disabledIntroNodeId]);
    $disabledConfig = json_decode(
        (string) $disabledNode->fetchColumn(),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    if (($disabledConfig['include_content'] ?? null) !== false) {
        throw new RuntimeException('Disabled Infosystem intro configuration changed unexpectedly.');
    }

    if ($runner->migrate('documents') !== []) {
        throw new RuntimeException('Applied Documents content migration was executed twice.');
    }
} finally {
    $deleteNodes = $db->prepare('DELETE FROM nodes WHERE site_id = :site_id');
    $deleteNodes->execute(['site_id' => $siteId]);

    $deleteInfosystems = $db->prepare('DELETE FROM infosystems WHERE site_id = :site_id');
    $deleteInfosystems->execute(['site_id' => $siteId]);

    $deleteSite = $db->prepare('DELETE FROM sites WHERE id = :site_id');
    $deleteSite->execute(['site_id' => $siteId]);
}

fwrite(STDOUT, "DOCUMENTS CONTENT MIGRATION OK\n");
