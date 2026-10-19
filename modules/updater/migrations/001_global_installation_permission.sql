INSERT INTO permissions(code, name, global_only) VALUES('updates.apply', 'Установка обновлений CMS', TRUE) ON CONFLICT(code) DO UPDATE SET global_only = TRUE;
DELETE FROM role_permissions rp USING roles r, permissions p WHERE rp.role_id = r.id AND rp.permission_id = p.id AND r.code <> 'superadmin' AND p.code = 'updates.apply';
