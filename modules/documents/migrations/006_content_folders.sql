ALTER TABLE documents ADD COLUMN folder_id BIGINT NULL;
ALTER TABLE documents ADD COLUMN folder_scope TEXT GENERATED ALWAYS AS ('documents'::text) STORED;
ALTER TABLE documents ADD FOREIGN KEY (site_id, folder_scope, folder_id) REFERENCES content_folders(site_id, scope, id) ON DELETE RESTRICT;
CREATE INDEX documents_folder ON documents(site_id, folder_id);
