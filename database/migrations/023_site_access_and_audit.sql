CREATE TABLE user_sites (
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    site_id BIGINT NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    PRIMARY KEY(user_id, site_id)
);
CREATE INDEX user_sites_site ON user_sites(site_id, user_id);
-- Existing operators retain their original default-site access. New sites require explicit grants.
INSERT INTO user_sites(user_id, site_id) SELECT id, 1 FROM users;
ALTER TABLE audit_log ADD COLUMN site_id BIGINT NULL REFERENCES sites(id) ON DELETE CASCADE;
CREATE INDEX audit_log_site_recent ON audit_log(site_id, created_at DESC, id DESC);
-- Editing PHP and replacing CMS code are installation-wide powers, reserved for the trusted operator.
DELETE FROM role_permissions rp USING roles r, permissions p WHERE rp.role_id = r.id AND rp.permission_id = p.id AND r.code <> 'superadmin' AND p.code IN ('templates.code.edit', 'modules.manage');

ALTER TABLE permissions ADD COLUMN global_only BOOLEAN NOT NULL DEFAULT FALSE;
UPDATE permissions SET global_only = TRUE WHERE code IN ('templates.code.edit', 'modules.manage');
