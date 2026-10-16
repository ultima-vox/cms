<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\ModuleMigrationCoordinator;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

// Run only against the separate, disposable Core-only database created by CI.
$db = Database::connection();
if ((int) $db->query('SELECT COUNT(*) FROM nodes')->fetchColumn() !== 0
    || (int) $db->query('SELECT COUNT(*) FROM module_schema_migrations')->fetchColumn() !== 0) {
    throw new RuntimeException('Upgrade smoke requires an empty Core-only database.');
}

$html = '<section>Legacy Infosystem introduction</section>';
$statement = $db->prepare(
    <<<'SQL'
    INSERT INTO nodes (site_id, name, slug, path, title, content, status, page_type, page_config)
    VALUES (1, 'Legacy Catalog', 'legacy-catalog', '/legacy-catalog', 'Legacy Catalog', :content,
        'published', 'core.content', '{}'::jsonb)
    RETURNING id
    SQL
);
$statement->execute(['content' => $html]);
$nodeId = (int) $statement->fetchColumn();
$statement = $db->prepare(
    <<<'SQL'
    INSERT INTO node_module_bindings (node_id, module_code, binding_code, target_key)
    VALUES (:node_id, 'infosystem', 'primary', 'legacy-catalog')
    SQL
);
$statement->execute(['node_id' => $nodeId]);

$coordinator = new ModuleMigrationCoordinator($db, dirname(__DIR__));
$result = $coordinator->migrateEnabled();
$codes = array_keys($result);
if (array_search('infosystem', $codes, true) >= array_search('documents', $codes, true)) {
    throw new RuntimeException('Legacy upgrade did not migrate Infosystem before Documents.');
}

$statement = $db->prepare(
    <<<'SQL'
    SELECT n.page_type, v.content, primary_binding.target_key
    FROM nodes n
    JOIN node_module_bindings intro ON intro.node_id = n.id
        AND intro.module_code = 'documents' AND intro.binding_code = 'intro'
    JOIN documents d ON d.site_id = n.site_id AND d.code = intro.target_key
    JOIN document_versions v ON v.document_id = d.id AND v.status = 'published'
    JOIN node_module_bindings primary_binding ON primary_binding.node_id = n.id
        AND primary_binding.module_code = 'infosystem' AND primary_binding.binding_code = 'primary'
    WHERE n.id = :node_id
    SQL
);
$statement->execute(['node_id' => $nodeId]);
$row = $statement->fetch(PDO::FETCH_ASSOC);
if (!is_array($row)
    || $row['page_type'] !== 'infosystem.list'
    || $row['content'] !== $html
    || $row['target_key'] !== 'legacy-catalog') {
    throw new RuntimeException('Legacy upgrade lost Infosystem page ownership, introduction or binding.');
}
if ((int) $db->query(
    "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'nodes' AND column_name = 'content'"
)->fetchColumn() !== 0) {
    throw new RuntimeException('Legacy content column was not removed after preserving the introduction.');
}
if ($coordinator->migrateEnabled() !== []) {
    throw new RuntimeException('Legacy module upgrade is not idempotent.');
}

fwrite(STDOUT, "MODULE MIGRATION UPGRADE OK\n");
