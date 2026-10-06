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
    ON CONFLICT (site_id, code) DO NOTHING
    RETURNING id, site_id, code
),
resolved_documents AS (
    SELECT
        d.id,
        d.site_id,
        d.code,
        c.node_id,
        c.content,
        c.created_at,
        c.updated_at
    FROM inserted_documents d
    JOIN candidates c
      ON c.site_id = d.site_id
     AND c.document_code = d.code

    UNION ALL

    SELECT
        d.id,
        d.site_id,
        d.code,
        c.node_id,
        c.content,
        c.created_at,
        c.updated_at
    FROM documents d
    JOIN candidates c
      ON c.site_id = d.site_id
     AND c.document_code = d.code
    WHERE NOT EXISTS (
        SELECT 1
        FROM inserted_documents inserted
        WHERE inserted.site_id = d.site_id
          AND inserted.code = d.code
    )
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
        d.content,
        'published',
        d.created_at,
        d.updated_at
    FROM resolved_documents d
    WHERE NOT EXISTS (
        SELECT 1
        FROM document_versions v
        WHERE v.document_id = d.id
    )
    RETURNING document_id
)
UPDATE nodes n
SET page_type = 'documents.page',
    page_config = jsonb_build_object('document', 'node-' || n.id::text),
    updated_at = CURRENT_TIMESTAMP
WHERE n.page_type = 'core.content'
  AND EXISTS (
      SELECT 1
      FROM resolved_documents d
      WHERE d.node_id = n.id
  )
  AND (
      EXISTS (
          SELECT 1
          FROM document_versions v
          JOIN resolved_documents d ON d.id = v.document_id
          WHERE d.node_id = n.id
      )
      OR EXISTS (
          SELECT 1
          FROM inserted_versions v
          JOIN resolved_documents d ON d.id = v.document_id
          WHERE d.node_id = n.id
      )
  );
