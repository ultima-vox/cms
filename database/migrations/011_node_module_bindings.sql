CREATE TABLE node_module_bindings (
    node_id BIGINT NOT NULL REFERENCES nodes(id) ON DELETE CASCADE,
    module_code VARCHAR(120) NOT NULL,
    binding_code VARCHAR(120) NOT NULL,
    target_key VARCHAR(255) NOT NULL,
    config JSONB NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(config) = 'object'),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (node_id, module_code, binding_code),
    CHECK (module_code ~ '^[a-z][a-z0-9_-]{0,119}$'),
    CHECK (binding_code ~ '^[a-z][a-z0-9_.-]{0,119}$'),
    CHECK (target_key <> '')
);

CREATE INDEX idx_node_module_bindings_target
    ON node_module_bindings (module_code, binding_code, target_key, node_id);

INSERT INTO node_module_bindings (node_id, module_code, binding_code, target_key)
SELECT n.id, 'infosystem', 'primary', i.code
FROM nodes n
JOIN infosystems i ON i.id = n.infosystem_id
WHERE n.infosystem_id IS NOT NULL;

DROP TRIGGER IF EXISTS trg_nodes_site_consistency ON nodes;

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

    RETURN NEW;
END
$$;

ALTER TABLE nodes DROP COLUMN infosystem_id;

CREATE TRIGGER trg_nodes_site_consistency
BEFORE INSERT OR UPDATE OF site_id, parent_id
ON nodes
FOR EACH ROW
EXECUTE FUNCTION enforce_node_site_consistency();
