ALTER TABLE document_versions
    ADD COLUMN author_user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL;
