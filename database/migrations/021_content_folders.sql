CREATE TABLE content_folders (
    id BIGSERIAL PRIMARY KEY,
    site_id BIGINT NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    scope VARCHAR(80) NOT NULL CHECK (scope ~ '^[a-z][a-z0-9._-]{0,79}$'),
    parent_id BIGINT NULL,
    name VARCHAR(160) NOT NULL CHECK (length(btrim(name)) > 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (site_id, scope, id),
    FOREIGN KEY (site_id, scope, parent_id) REFERENCES content_folders(site_id, scope, id) ON DELETE RESTRICT,
    CHECK (parent_id IS NULL OR parent_id <> id)
);
CREATE UNIQUE INDEX content_folders_sibling_name ON content_folders(site_id, scope, COALESCE(parent_id, 0), lower(name));
CREATE INDEX content_folders_parent ON content_folders(site_id, scope, parent_id);
CREATE FUNCTION guard_content_folder_tree() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE invalid BOOLEAN; parent_depth INTEGER; subtree_depth INTEGER;
BEGIN
    PERFORM pg_advisory_xact_lock(hashtext('folders:' || NEW.site_id || ':' || NEW.scope));
    IF TG_OP = 'UPDATE' AND (OLD.site_id <> NEW.site_id OR OLD.scope <> NEW.scope) THEN
        RAISE EXCEPTION 'Folder site and scope are immutable.';
    END IF;
    WITH RECURSIVE chain AS (
        SELECT id, parent_id, 1 AS depth FROM content_folders WHERE id = NEW.parent_id
        UNION ALL
        SELECT p.id, p.parent_id, c.depth + 1 FROM content_folders p JOIN chain c ON p.id = c.parent_id WHERE c.depth < 64 AND c.id <> NEW.id
    ) SELECT COALESCE(bool_or(id = NEW.id OR depth >= 64), FALSE), COALESCE(max(depth), 0) INTO invalid, parent_depth FROM chain;
    IF invalid THEN RAISE EXCEPTION 'Folder cycle or maximum depth exceeded.'; END IF;
    WITH RECURSIVE descendants AS (
        SELECT id, 2 AS depth FROM content_folders WHERE parent_id = NEW.id
        UNION ALL
        SELECT f.id, d.depth + 1 FROM content_folders f JOIN descendants d ON f.parent_id = d.id WHERE d.depth < 64
    ) SELECT COALESCE(max(depth), 1) INTO subtree_depth FROM descendants;
    IF parent_depth + subtree_depth > 64 THEN RAISE EXCEPTION 'Folder subtree exceeds maximum depth.'; END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER content_folders_tree BEFORE INSERT OR UPDATE ON content_folders FOR EACH ROW EXECUTE FUNCTION guard_content_folder_tree();
