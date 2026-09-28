BEGIN;

CREATE TABLE layouts (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    template_path VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE infosystems (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(120) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE nodes (
    id BIGSERIAL PRIMARY KEY,
    parent_id BIGINT REFERENCES nodes(id) ON DELETE CASCADE,
    layout_id BIGINT REFERENCES layouts(id) ON DELETE SET NULL,
    infosystem_id BIGINT REFERENCES infosystems(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL,
    path VARCHAR(1024) NOT NULL UNIQUE,
    title VARCHAR(255),
    content TEXT NOT NULL DEFAULT '',
    meta_description VARCHAR(320),
    status VARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'archived')),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sorting INTEGER NOT NULL DEFAULT 0,
    publish_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (left(path, 1) = '/')
);

CREATE TABLE infosystem_items (
    id BIGSERIAL PRIMARY KEY,
    infosystem_id BIGINT NOT NULL REFERENCES infosystems(id) ON DELETE CASCADE,
    parent_id BIGINT REFERENCES infosystem_items(id) ON DELETE CASCADE,
    is_group BOOLEAN NOT NULL DEFAULT FALSE,
    name VARCHAR(255) NOT NULL,
    path VARCHAR(1024) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'archived')),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sorting INTEGER NOT NULL DEFAULT 0,
    properties JSONB NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(properties) = 'object'),
    publish_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (infosystem_id, path)
);

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(320) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(150) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE user_roles (
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE role_permissions (
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE audit_log (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(150) NOT NULL,
    entity_type VARCHAR(100),
    entity_id BIGINT,
    context JSONB NOT NULL DEFAULT '{}'::jsonb,
    ip_address INET,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_nodes_parent_sorting ON nodes(parent_id, sorting, id);
CREATE INDEX idx_nodes_publication ON nodes(is_active, status, publish_at);
CREATE INDEX idx_items_infosystem_parent_sorting ON infosystem_items(infosystem_id, parent_id, sorting, id);
CREATE INDEX idx_items_publication ON infosystem_items(infosystem_id, is_active, status, publish_at);
CREATE INDEX idx_items_properties_gin ON infosystem_items USING GIN (properties);
CREATE INDEX idx_audit_log_user_created ON audit_log(user_id, created_at DESC);
CREATE INDEX idx_audit_log_entity ON audit_log(entity_type, entity_id, created_at DESC);

INSERT INTO roles (code, name) VALUES
    ('superadmin', 'Super Administrator'),
    ('admin', 'Administrator'),
    ('editor', 'Content Editor');

COMMIT;
