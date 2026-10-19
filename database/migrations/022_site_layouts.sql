ALTER TABLE layouts ADD COLUMN site_id BIGINT NOT NULL DEFAULT 1 REFERENCES sites(id) ON DELETE CASCADE;
ALTER TABLE layouts DROP CONSTRAINT layouts_template_path_key;
DROP INDEX idx_layouts_code;
ALTER TABLE layouts DROP CONSTRAINT layouts_parent_id_fkey;
CREATE UNIQUE INDEX layouts_site_code ON layouts(site_id, code);
CREATE UNIQUE INDEX layouts_site_template ON layouts(site_id, template_path);
ALTER TABLE layouts ADD UNIQUE (site_id, id);
CREATE TEMP TABLE layout_site_mapping(site_id BIGINT, old_id BIGINT, new_id BIGINT, old_parent BIGINT) ON COMMIT DROP;
INSERT INTO layout_site_mapping SELECT 1, id, id, parent_id FROM layouts;
DO $$
DECLARE site RECORD; source RECORD; target BIGINT;
BEGIN
    FOR site IN SELECT id FROM sites WHERE id <> 1 LOOP
        FOR source IN SELECT * FROM layouts WHERE site_id = 1 ORDER BY id LOOP
            INSERT INTO layouts(site_id, code, name, template_path, description, is_system)
            VALUES(site.id, source.code, source.name, source.template_path, source.description, source.is_system) RETURNING id INTO target;
            INSERT INTO layout_site_mapping VALUES(site.id, source.id, target, source.parent_id);
        END LOOP;
    END LOOP;
END $$;
UPDATE layouts l SET parent_id = parent.new_id FROM layout_site_mapping m JOIN layout_site_mapping parent ON parent.site_id = m.site_id AND parent.old_id = m.old_parent WHERE l.id = m.new_id;
UPDATE nodes n SET layout_id = m.new_id FROM layout_site_mapping m WHERE n.site_id = m.site_id AND n.layout_id = m.old_id;
ALTER TABLE layouts ADD FOREIGN KEY(site_id, parent_id) REFERENCES layouts(site_id, id) ON DELETE RESTRICT;
ALTER TABLE nodes DROP CONSTRAINT nodes_layout_id_fkey;
ALTER TABLE nodes ADD FOREIGN KEY(site_id, layout_id) REFERENCES layouts(site_id, id) ON DELETE RESTRICT;
ALTER TABLE layouts ADD COLUMN folder_id BIGINT NULL;
ALTER TABLE layouts ADD COLUMN folder_scope TEXT GENERATED ALWAYS AS ('layouts'::text) STORED;
ALTER TABLE layouts ADD FOREIGN KEY(site_id, folder_scope, folder_id) REFERENCES content_folders(site_id, scope, id) ON DELETE RESTRICT;
CREATE INDEX layouts_folder ON layouts(site_id, folder_id);
UPDATE sites SET settings = settings || '{"legacy_layout_overrides":true}'::jsonb;
CREATE FUNCTION seed_site_layout() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO layouts(site_id, code, name, template_path, description, is_system)
    VALUES(NEW.id, 'main', 'Основной макет', 'layouts/main.php', 'Штатный макет сайта.', TRUE);
    RETURN NEW;
END $$;
CREATE TRIGGER sites_default_layout AFTER INSERT ON sites FOR EACH ROW EXECUTE FUNCTION seed_site_layout();
