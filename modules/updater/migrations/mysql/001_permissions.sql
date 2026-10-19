-- MySQL/MariaDB legacy schema. PostgreSQL migrations remain unchanged.
INSERT INTO permissions(code, name, global_only) VALUES('updates.apply', 'Установка обновлений CMS', TRUE) ON DUPLICATE KEY UPDATE global_only = TRUE;
DELETE FROM role_permissions WHERE role_id IN (SELECT id FROM roles WHERE code <> 'superadmin') AND permission_id IN (SELECT id FROM permissions WHERE code = 'updates.apply');
