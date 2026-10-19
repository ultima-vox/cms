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
DECLARE invalid BOOLEAN;
BEGIN
    PERFORM pg_advisory_xact_lock(hashtext('folders:' || NEW.site_id || ':' || NEW.scope));
    IF TG_OP = 'UPDATE' AND (OLD.site_id <> NEW.site_id OR OLD.scope <> NEW.scope) THEN
        RAISE EXCEPTION 'Folder site and scope are immutable.';
    END IF;
    WITH RECURSIVE chain AS (
        SELECT id, parent_id, 1 AS depth FROM content_folders WHERE id = NEW.parent_id
        UNION ALL
        SELECT p.id, p.parent_id, c.depth + 1 FROM content_folders p JOIN chain c ON p.id = c.parent_id WHERE c.depth <= 64 AND c.id <> NEW.id
    ) SELECT EXISTS(SELECT 1 FROM chain WHERE id = NEW.id OR depth > 64) INTO invalid;
    IF invalid THEN RAISE EXCEPTION 'Folder cycle or maximum depth exceeded.'; END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER content_folders_tree BEFORE INSERT OR UPDATE ON content_folders FOR EACH ROW EXECUTE FUNCTION guard_content_folder_tree();
