ALTER TABLE layouts
    ADD COLUMN IF NOT EXISTS parent_id BIGINT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'layouts_parent_id_fkey'
    ) THEN
        ALTER TABLE layouts
            ADD CONSTRAINT layouts_parent_id_fkey
            FOREIGN KEY (parent_id)
            REFERENCES layouts(id)
            ON DELETE RESTRICT;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'layouts_parent_not_self'
    ) THEN
        ALTER TABLE layouts
            ADD CONSTRAINT layouts_parent_not_self
            CHECK (parent_id IS NULL OR parent_id <> id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS layouts_parent_id_idx
    ON layouts (parent_id);
