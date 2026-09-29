CREATE TABLE sites (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    settings JSONB NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(settings) = 'object'),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (code ~ '^[a-z][a-z0-9_-]{0,119}$')
);

INSERT INTO sites (id, code, name, is_active)
VALUES (1, 'default', 'Default site', TRUE);

SELECT setval(
    pg_get_serial_sequence('sites', 'id'),
    GREATEST(COALESCE((SELECT MAX(id) FROM sites), 1), 1),
    TRUE
);

CREATE TABLE site_domains (
    id BIGSERIAL PRIMARY KEY,
    site_id BIGINT NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    host VARCHAR(253) NOT NULL UNIQUE,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (host = lower(host)),
    CHECK (host ~ '^[a-z0-9.-]{1,253}$')
);

CREATE UNIQUE INDEX uq_site_domains_primary
    ON site_domains(site_id)
    WHERE is_primary = TRUE;

ALTER TABLE nodes
    ADD COLUMN site_id BIGINT NOT NULL DEFAULT 1 REFERENCES sites(id) ON DELETE RESTRICT;

ALTER TABLE infosystems
    ADD COLUMN site_id BIGINT NOT NULL DEFAULT 1 REFERENCES sites(id) ON DELETE RESTRICT;

ALTER TABLE nodes DROP CONSTRAINT IF EXISTS nodes_path_key;
ALTER TABLE infosystems DROP CONSTRAINT IF EXISTS infosystems_code_key;
DROP INDEX IF EXISTS idx_nodes_single_root;
DROP INDEX IF EXISTS idx_nodes_parent_slug;

CREATE UNIQUE INDEX uq_nodes_site_path
    ON nodes(site_id, path);

CREATE UNIQUE INDEX uq_nodes_site_root
    ON nodes(site_id)
    WHERE parent_id IS NULL;

CREATE UNIQUE INDEX uq_nodes_site_parent_slug
    ON nodes(site_id, parent_id, slug)
    WHERE parent_id IS NOT NULL;

CREATE UNIQUE INDEX uq_infosystems_site_code
    ON infosystems(site_id, code);

CREATE INDEX idx_nodes_site_publication
    ON nodes(site_id, is_active, status, publish_at);

CREATE INDEX idx_infosystems_site_active
    ON infosystems(site_id, is_active, id);

CREATE OR REPLACE FUNCTION enforce_node_site_consistency()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.parent_id IS NOT NULL
       AND NOT EXISTS (
           SELECT 1
           FROM nodes parent
           WHERE parent.id = NEW.parent_id
             AND parent.site_id = NEW.site_id
       ) THEN
        RAISE EXCEPTION 'Node parent must belong to the same site.';
    END IF;

    IF NEW.infosystem_id IS NOT NULL
       AND NOT EXISTS (
           SELECT 1
           FROM infosystems i
           WHERE i.id = NEW.infosystem_id
             AND i.site_id = NEW.site_id
       ) THEN
        RAISE EXCEPTION 'Node infosystem must belong to the same site.';
    END IF;

    RETURN NEW;
END
$$;

CREATE TRIGGER trg_nodes_site_consistency
BEFORE INSERT OR UPDATE OF site_id, parent_id, infosystem_id
ON nodes
FOR EACH ROW
EXECUTE FUNCTION enforce_node_site_consistency();
