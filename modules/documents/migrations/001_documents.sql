CREATE TABLE documents (
    id BIGSERIAL PRIMARY KEY,
    site_id BIGINT NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    code VARCHAR(120) NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT documents_code_format CHECK (code ~ '^[a-z][a-z0-9_-]{0,119}$'),
    CONSTRAINT documents_site_code_unique UNIQUE (site_id, code)
);

CREATE TABLE document_versions (
    id BIGSERIAL PRIMARY KEY,
    document_id BIGINT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    version INTEGER NOT NULL CHECK (version > 0),
    content TEXT NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft', 'published', 'archived')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at TIMESTAMPTZ,
    CONSTRAINT document_versions_document_version_unique UNIQUE (document_id, version),
    CONSTRAINT document_versions_publish_state CHECK (
        (status = 'published' AND published_at IS NOT NULL)
        OR status <> 'published'
    )
);

CREATE UNIQUE INDEX idx_document_versions_one_published
    ON document_versions (document_id)
    WHERE status = 'published';

CREATE INDEX idx_documents_site_active
    ON documents (site_id, is_active, code, id);

CREATE INDEX idx_document_versions_document_status
    ON document_versions (document_id, status, version DESC, id DESC);
