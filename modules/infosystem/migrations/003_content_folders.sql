ALTER TABLE infosystems ADD COLUMN folder_id BIGINT NULL;
ALTER TABLE infosystems ADD COLUMN folder_scope TEXT GENERATED ALWAYS AS ('infosystems'::text) STORED;
ALTER TABLE infosystems ADD FOREIGN KEY (site_id, folder_scope, folder_id) REFERENCES content_folders(site_id, scope, id) ON DELETE RESTRICT;
CREATE INDEX infosystems_folder ON infosystems(site_id, folder_id);
