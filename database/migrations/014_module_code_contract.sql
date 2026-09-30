ALTER TABLE module_schema_migrations
    DROP CONSTRAINT IF EXISTS module_schema_migrations_module_code_check;

ALTER TABLE module_schema_migrations
    ADD CONSTRAINT module_schema_migrations_module_code_check
    CHECK (module_code ~ '^[a-z][a-z0-9._-]{0,79}$');
