CREATE TABLE IF NOT EXISTS installed_modules (
    code VARCHAR(80) PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    version VARCHAR(64) NOT NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    installed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_installed_modules_enabled
    ON installed_modules (is_enabled, code);
