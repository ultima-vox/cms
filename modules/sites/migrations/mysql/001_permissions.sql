-- MySQL/MariaDB legacy schema. PostgreSQL migrations remain unchanged.
INSERT INTO permissions(code, name, global_only) VALUES ('sites.create', 'Создание сайтов', TRUE), ('sites.access.manage', 'Назначение доступа пользователей к сайтам', TRUE) ON DUPLICATE KEY UPDATE global_only = TRUE;
INSERT INTO role_permissions(role_id, permission_id) SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'superadmin' AND p.code IN ('sites.create', 'sites.access.manage') ON DUPLICATE KEY UPDATE role_id = role_id;

