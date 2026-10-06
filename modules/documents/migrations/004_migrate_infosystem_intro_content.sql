WITH eligible AS MATERIALIZED (
    SELECT
        n.id AS node_id,
        n.site_id,
        n.name,
        n.title,
        n.content,
        n.created_at,
        n.updated_at,
        'node-' || n.id::text || '-intro' AS document_code
    FROM nodes n
    WHERE n.page_type = 'infosystem.list'
      AND (
          NOT (n.page_config ? 'include_content')
          OR n.page_config->'include_content' = 'true'::jsonb
      )
),
candidates AS MATERIALIZED (
    SELECT e.*
    FROM eligible e
    WHERE BTRIM(e.content) <> ''
      AND NOT EXISTS (
          SELECT 1
          FROM node_module_bindings b
          WHERE b.node_id = e.node_id
            AND b.module_code = 'documents'
            AND b.binding_code = 'intro'
      )
),
inserted_documents AS (
    INSERT INTO documents (
        site_id,
        code,
        name,
        is_active,
        created_at,
        updated_at
    )
    SELECT
        c.site_id,
        c.document_code,
        COALESCE(NULLIF(BTRIM(c.title), ''), c.name) || ' — intro',
        TRUE,
        c.created_at,
        c.updated_at
    FROM candidates c
    RETURNING id, site_id, code
),
inserted_versions AS (
    INSERT INTO document_versions (
        document_id,
        version,
        content,
        status,
        created_at,
        published_at
    )
    SELECT
        d.id,
        1,
        c.content,
        'published',
        c.created_at,
        c.updated_at
    FROM inserted_documents d
    JOIN candidates c
      ON c.site_id = d.site_id
     AND c.document_code = d.code
    RETURNING document_id
),
inserted_bindings AS (
    INSERT INTO node_module_bindings (
        node_id,
        module_code,
        binding_code,
        target_key
    )
    SELECT
        c.node_id,
        'documents',
        'intro',
        c.document_code
    FROM candidates c
    JOIN inserted_documents d
      ON d.site_id = c.site_id
     AND d.code = c.document_code
    WHERE EXISTS (
        SELECT 1
        FROM inserted_versions v
        WHERE v.document_id = d.id
    )
    RETURNING node_id
)
UPDATE nodes n
SET page_config = jsonb_set(n.page_config, '{include_content}', 'false'::jsonb, TRUE),
    updated_at = CURRENT_TIMESTAMP
WHERE n.id IN (SELECT node_id FROM eligible)
  AND (
      BTRIM(n.content) = ''
      OR EXISTS (
          SELECT 1
          FROM node_module_bindings b
          WHERE b.node_id = n.id
            AND b.module_code = 'documents'
            AND b.binding_code = 'intro'
      )
      OR EXISTS (
          SELECT 1
          FROM inserted_bindings b
          WHERE b.node_id = n.id
      )
  );
