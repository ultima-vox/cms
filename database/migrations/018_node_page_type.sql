ALTER TABLE nodes
    ADD COLUMN IF NOT EXISTS page_type VARCHAR(128) NOT NULL DEFAULT 'core.content',
    ADD COLUMN IF NOT EXISTS page_config JSONB NOT NULL DEFAULT '{}'::jsonb;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'nodes_page_type_format'
          AND conrelid = 'nodes'::regclass
    ) THEN
        ALTER TABLE nodes
            ADD CONSTRAINT nodes_page_type_format
            CHECK (page_type ~ '^[a-z][a-z0-9._-]{0,127}$');
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'nodes_page_config_object'
          AND conrelid = 'nodes'::regclass
    ) THEN
        ALTER TABLE nodes
            ADD CONSTRAINT nodes_page_config_object
            CHECK (jsonb_typeof(page_config) = 'object');
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS nodes_page_type_idx
    ON nodes (page_type);
