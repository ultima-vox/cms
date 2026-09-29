ALTER TABLE infosystems
    ADD COLUMN field_schema JSONB NOT NULL DEFAULT '[]'::jsonb
        CHECK (jsonb_typeof(field_schema) = 'array'),
    ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE;

CREATE TABLE infosystem_groups (
    id BIGSERIAL PRIMARY KEY,
    infosystem_id BIGINT NOT NULL REFERENCES infosystems(id) ON DELETE CASCADE,
    parent_id BIGINT REFERENCES infosystem_groups(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    path VARCHAR(1024) NOT NULL,
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sorting INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (infosystem_id, path),
    CHECK (slug ~ '^[a-z0-9][a-z0-9-]*$'),
    CHECK (left(path, 1) = '/')
);

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM infosystem_items child
        JOIN infosystem_items parent ON parent.id = child.parent_id
        WHERE child.is_group = FALSE
          AND parent.is_group = FALSE
    ) THEN
        RAISE EXCEPTION 'Legacy infosystem items contain item-to-item parent relations; migration requires manual cleanup.';
    END IF;
END
$$;

INSERT INTO infosystem_groups (
    id, infosystem_id, parent_id, name, slug, path, is_active, sorting, created_at, updated_at
)
SELECT
    id,
    infosystem_id,
    NULL,
    name,
    regexp_replace(trim(both '/' from path), '^.*/', ''),
    path,
    is_active,
    sorting,
    created_at,
    updated_at
FROM infosystem_items
WHERE is_group = TRUE;

UPDATE infosystem_groups g
SET parent_id = legacy.parent_id
FROM infosystem_items legacy
WHERE legacy.id = g.id
  AND legacy.parent_id IS NOT NULL
  AND EXISTS (SELECT 1 FROM infosystem_groups parent_group WHERE parent_group.id = legacy.parent_id);

SELECT setval(
    pg_get_serial_sequence('infosystem_groups', 'id'),
    GREATEST(COALESCE((SELECT MAX(id) FROM infosystem_groups), 1), 1),
    TRUE
);

ALTER TABLE infosystem_items
    ADD COLUMN group_id BIGINT REFERENCES infosystem_groups(id) ON DELETE SET NULL,
    ADD COLUMN slug VARCHAR(255),
    ADD COLUMN description TEXT,
    ADD COLUMN content TEXT NOT NULL DEFAULT '',
    ADD COLUMN meta_description VARCHAR(320);

UPDATE infosystem_items item
SET group_id = item.parent_id
WHERE item.is_group = FALSE
  AND item.parent_id IS NOT NULL
  AND EXISTS (SELECT 1 FROM infosystem_groups g WHERE g.id = item.parent_id);

UPDATE infosystem_items
SET slug = regexp_replace(trim(both '/' from path), '^.*/', '')
WHERE is_group = FALSE;

DELETE FROM infosystem_items WHERE is_group = TRUE;

ALTER TABLE infosystem_items
    DROP COLUMN parent_id,
    DROP COLUMN is_group,
    ALTER COLUMN slug SET NOT NULL,
    ADD CONSTRAINT infosystem_items_slug_check CHECK (slug ~ '^[a-z0-9][a-z0-9-]*$');

DROP INDEX IF EXISTS idx_items_infosystem_parent_sorting;
CREATE INDEX idx_infosystem_groups_parent_sorting
    ON infosystem_groups(infosystem_id, parent_id, sorting, id);
CREATE UNIQUE INDEX uq_infosystem_groups_sibling_slug
    ON infosystem_groups(infosystem_id, COALESCE(parent_id, 0), slug);
CREATE INDEX idx_items_infosystem_group_sorting
    ON infosystem_items(infosystem_id, group_id, sorting, id);
CREATE UNIQUE INDEX uq_infosystem_items_group_slug
    ON infosystem_items(infosystem_id, COALESCE(group_id, 0), slug);
CREATE INDEX idx_infosystems_active ON infosystems(is_active, id);
