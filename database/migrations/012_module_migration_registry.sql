CREATE TABLE module_schema_migrations (
    module_code VARCHAR(120) NOT NULL,
    migration VARCHAR(255) NOT NULL,
    checksum CHAR(64) NOT NULL,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (module_code, migration),
    CHECK (module_code ~ '^[a-z][a-z0-9_-]{0,119}$'),
    CHECK (migration ~ '^[A-Za-z0-9][A-Za-z0-9._-]{0,254}[.]sql$'),
    CHECK (checksum ~ '^[0-9a-f]{64}$')
);

CREATE INDEX idx_module_schema_migrations_applied
    ON module_schema_migrations (module_code, applied_at, migration);
