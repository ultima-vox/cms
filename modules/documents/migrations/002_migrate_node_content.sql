WITH candidates AS MATERIALIZED (
    SELECT
        n.id AS node_id,
        n.site_id,
        n.name,
        n.title,
        n.content,
        n.created_at,
        n.updated_at,
        'node-' || n.id::text AS document_code
    FROM nodes n
    WHERE n.page_type = 'core.content'
      AND NOT EXISTS (
          SELECT 1
          FROM node_module_bindings b
          WHERE b.node_id = n.id
            AND b.binding_code = 'primary'
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
        COALESCE(NULLIF(BTRIM(c.title), ''), c.name),
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
)
UPDATE nodes n
SET page_type = 'documents.page',
    page_config = jsonb_build_object('document', 'node-' || n.id::text),
    updated_at = CURRENT_TIMESTAMP
WHERE n.page_type = 'core.content'
  AND NOT EXISTS (
      SELECT 1
      FROM node_module_bindings b
      WHERE b.node_id = n.id
        AND b.binding_code = 'primary'
  )
  AND EXISTS (
      SELECT 1
      FROM inserted_documents d
      JOIN inserted_versions v ON v.document_id = d.id
      WHERE d.site_id = n.site_id
        AND d.code = 'node-' || n.id::text
  );
