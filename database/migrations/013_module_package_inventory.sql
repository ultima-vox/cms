CREATE TABLE module_package_inventory (
    module_code VARCHAR(120) PRIMARY KEY,
    version VARCHAR(64) NOT NULL,
    package_sha256 CHAR(64) NOT NULL,
    source VARCHAR(32) NOT NULL DEFAULT 'package',
    installed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (module_code ~ '^[a-z][a-z0-9._-]{0,79}$'),
    CHECK (package_sha256 ~ '^[0-9a-f]{64}$'),
    CHECK (source IN ('package'))
);

CREATE INDEX idx_module_package_inventory_updated
    ON module_package_inventory (updated_at DESC, module_code);
