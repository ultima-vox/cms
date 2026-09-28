CREATE TABLE login_attempts (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(320) NOT NULL,
    ip_address INET NOT NULL,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_login_attempts_identity_time
    ON login_attempts(email, ip_address, attempted_at DESC);

INSERT INTO permissions (code, name) VALUES
    ('admin.access', 'Access administration panel'),
    ('structure.manage', 'Manage site structure'),
    ('infosystems.manage', 'Manage infosystems'),
    ('layouts.manage', 'Manage layouts'),
    ('users.manage', 'Manage users')
ON CONFLICT (code) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.code = 'superadmin'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN ('admin.access', 'structure.manage', 'infosystems.manage', 'layouts.manage')
WHERE r.code = 'admin'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN ('admin.access', 'structure.manage', 'infosystems.manage')
WHERE r.code = 'editor'
ON CONFLICT DO NOTHING;
