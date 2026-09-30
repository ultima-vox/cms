CREATE TABLE installed_modules (
    code VARCHAR(80) PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    version VARCHAR(64) NOT NULL,
    extension_api VARCHAR(128) NOT NULL,
    is_enabled BOOLEAN NOT NULL,
    installed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_installed_modules_enabled
    ON installed_modules (is_enabled, code);
