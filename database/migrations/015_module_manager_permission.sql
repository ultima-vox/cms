INSERT INTO permissions (code, name)
VALUES ('modules.manage', 'Manage installed modules and package lifecycle')
ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code = 'modules.manage'
WHERE r.code = 'superadmin'
ON CONFLICT DO NOTHING;
