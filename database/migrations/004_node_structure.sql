ALTER TABLE nodes ADD COLUMN slug VARCHAR(255);

UPDATE nodes
SET slug = CASE
    WHEN path = '/' THEN ''
    ELSE regexp_replace(trim(trailing '/' FROM path), '^.*/', '')
END;

ALTER TABLE nodes ALTER COLUMN slug SET NOT NULL;

ALTER TABLE nodes ADD CONSTRAINT nodes_slug_format CHECK (
    (parent_id IS NULL AND slug = '')
    OR
    (parent_id IS NOT NULL AND slug ~ '^[a-z0-9][a-z0-9-]{0,254}$')
);

CREATE UNIQUE INDEX idx_nodes_single_root
    ON nodes ((1))
    WHERE parent_id IS NULL;

CREATE UNIQUE INDEX idx_nodes_parent_slug
    ON nodes (parent_id, slug)
    WHERE parent_id IS NOT NULL;

CREATE INDEX idx_nodes_tree_order
    ON nodes (parent_id, sorting, name, id);
