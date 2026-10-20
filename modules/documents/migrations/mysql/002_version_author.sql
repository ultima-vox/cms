ALTER TABLE document_versions
    ADD COLUMN author_user_id BIGINT NULL,
    ADD CONSTRAINT document_versions_author_fk FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL;
