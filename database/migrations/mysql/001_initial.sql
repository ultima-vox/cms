-- MySQL/MariaDB legacy schema. PostgreSQL migrations remain unchanged.
CREATE TABLE runtime_locks (
lock_key CHAR(64) PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE sites (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, code VARCHAR(120) NOT NULL UNIQUE, name VARCHAR(255) NOT NULL,
is_active BOOLEAN NOT NULL DEFAULT TRUE, settings JSON NOT NULL DEFAULT ('{}'), created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
CHECK (JSON_TYPE(settings) = 'OBJECT'), CHECK(code REGEXP '^[a-z][a-z0-9_-]{0,119}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
INSERT INTO sites(id,code,name) VALUES(1,'default','Default site');
-- cms:statement
CREATE TABLE users (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, email VARCHAR(320) NOT NULL, email_normalized VARCHAR(320) GENERATED ALWAYS AS (LOWER(email)) STORED UNIQUE,
password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(255) NOT NULL,
is_active BOOLEAN NOT NULL DEFAULT TRUE, last_login_at DATETIME(6), created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE roles (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, code VARCHAR(100) NOT NULL UNIQUE, name VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE permissions (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, code VARCHAR(150) NOT NULL UNIQUE, name VARCHAR(255) NOT NULL, global_only BOOLEAN NOT NULL DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE user_roles (
user_id BIGINT NOT NULL, role_id BIGINT NOT NULL, PRIMARY KEY(user_id,role_id),
FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE role_permissions (
role_id BIGINT NOT NULL, permission_id BIGINT NOT NULL, PRIMARY KEY(role_id,permission_id),
FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE, FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE content_folders (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, site_id BIGINT NOT NULL, scope VARCHAR(80) NOT NULL, parent_id BIGINT NULL,
name VARCHAR(160) NOT NULL, parent_key BIGINT GENERATED ALWAYS AS (COALESCE(parent_id,0)) STORED,
name_key VARCHAR(160) GENERATED ALWAYS AS (LOWER(name)) STORED, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
UNIQUE(site_id,scope,id), UNIQUE(site_id,scope,parent_key,name_key),
FOREIGN KEY(site_id) REFERENCES sites(id) ON DELETE CASCADE,
FOREIGN KEY(site_id,scope,parent_id) REFERENCES content_folders(site_id,scope,id) ON DELETE RESTRICT,
CHECK(scope REGEXP '^[a-z][a-z0-9._-]{0,79}$'), CHECK(CHAR_LENGTH(TRIM(name)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE layouts (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, site_id BIGINT NOT NULL DEFAULT 1, code VARCHAR(80) NOT NULL, name VARCHAR(255) NOT NULL,
template_path VARCHAR(255) NOT NULL, description TEXT, is_system BOOLEAN NOT NULL DEFAULT FALSE, parent_id BIGINT NULL,
folder_id BIGINT NULL, folder_scope VARCHAR(80) GENERATED ALWAYS AS ('layouts') STORED, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
UNIQUE(site_id,code), UNIQUE(site_id,template_path), UNIQUE(site_id,id),
FOREIGN KEY(site_id) REFERENCES sites(id) ON DELETE CASCADE,
FOREIGN KEY(site_id,parent_id) REFERENCES layouts(site_id,id) ON DELETE RESTRICT,
FOREIGN KEY(site_id,folder_scope,folder_id) REFERENCES content_folders(site_id,scope,id) ON DELETE RESTRICT,
CHECK(code REGEXP '^[a-z0-9][a-z0-9_-]{0,79}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
INSERT INTO layouts(site_id,code,name,template_path,description,is_system) VALUES(1,'main','Основной макет','layouts/main.php','Штатный макет сайта.',TRUE);
-- cms:statement
CREATE TRIGGER sites_default_layout AFTER INSERT ON sites FOR EACH ROW
INSERT INTO layouts(site_id,code,name,template_path,description,is_system)
VALUES(NEW.id,'main','Основной макет','layouts/main.php','Штатный макет сайта.',TRUE);
-- cms:statement
CREATE TABLE nodes (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, site_id BIGINT NOT NULL, parent_id BIGINT NULL, layout_id BIGINT NULL,
name VARCHAR(255) NOT NULL, slug VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
path VARCHAR(1024) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, title VARCHAR(255), meta_description VARCHAR(320),
status VARCHAR(20) NOT NULL DEFAULT 'draft', is_active BOOLEAN NOT NULL DEFAULT TRUE, sorting INTEGER NOT NULL DEFAULT 0,
publish_at DATETIME(6), page_type VARCHAR(128) NOT NULL, page_config JSON NOT NULL DEFAULT ('{}'),
root_site BIGINT GENERATED ALWAYS AS (CASE WHEN parent_id IS NULL THEN site_id ELSE NULL END) VIRTUAL UNIQUE, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
UNIQUE(site_id,id), UNIQUE(site_id,path), UNIQUE(site_id,parent_id,slug),
FOREIGN KEY(site_id) REFERENCES sites(id) ON DELETE RESTRICT,
FOREIGN KEY(site_id,parent_id) REFERENCES nodes(site_id,id) ON DELETE CASCADE,
FOREIGN KEY(site_id,layout_id) REFERENCES layouts(site_id,id) ON DELETE RESTRICT,
CHECK(status IN ('draft','published','archived')), CHECK(LEFT(path,1)='/'),
CHECK(page_type REGEXP '^[a-z][a-z0-9._-]{0,127}$'), CHECK(JSON_TYPE(page_config)='OBJECT'),
CHECK(path='/' OR NOT(path='/admin' OR path LIKE '/admin/%' OR path='/api' OR path LIKE '/api/%' OR path='/health' OR path LIKE '/health/%' OR path='/assets' OR path LIKE '/assets/%' OR path='/media' OR path LIKE '/media/%' OR path='/storage' OR path LIKE '/storage/%')),
INDEX idx_nodes_publication(site_id,is_active,status,publish_at), INDEX idx_nodes_tree(site_id,parent_id,sorting,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE site_domains (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, site_id BIGINT NOT NULL, host VARCHAR(253) NOT NULL UNIQUE,
is_primary BOOLEAN NOT NULL DEFAULT FALSE, primary_site BIGINT GENERATED ALWAYS AS (CASE WHEN is_primary THEN site_id ELSE NULL END) VIRTUAL UNIQUE,
created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), FOREIGN KEY(site_id) REFERENCES sites(id) ON DELETE CASCADE,
CHECK(host=LOWER(host)), CHECK(host REGEXP '^[a-z0-9.-]{1,253}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE user_sites (
user_id BIGINT NOT NULL,site_id BIGINT NOT NULL,PRIMARY KEY(user_id,site_id), INDEX user_sites_site(site_id,user_id),
FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE audit_log (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, site_id BIGINT NULL, user_id BIGINT NULL, action VARCHAR(150) NOT NULL,
entity_type VARCHAR(100),entity_id BIGINT, context JSON NOT NULL DEFAULT ('{}'),ip_address VARCHAR(45),
created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
FOREIGN KEY(site_id) REFERENCES sites(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
INDEX audit_log_site_recent(site_id,created_at,id), INDEX audit_log_entity(entity_type,entity_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE login_attempts (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,email VARCHAR(320) NOT NULL,ip_address VARCHAR(45) NOT NULL,
attempted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
INDEX login_attempts_identity(email,ip_address,attempted_at), INDEX login_attempts_ip(ip_address,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE installed_modules (
code VARCHAR(80) PRIMARY KEY,name VARCHAR(120) NOT NULL,version VARCHAR(64) NOT NULL,extension_api VARCHAR(128) NOT NULL,
is_enabled BOOLEAN NOT NULL,installed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE node_module_bindings (
node_id BIGINT NOT NULL,module_code VARCHAR(120) NOT NULL,binding_code VARCHAR(120) NOT NULL,target_key VARCHAR(255) NOT NULL,
config JSON NOT NULL DEFAULT ('{}'),created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),PRIMARY KEY(node_id,module_code,binding_code),
FOREIGN KEY(node_id) REFERENCES nodes(id) ON DELETE CASCADE,CHECK(JSON_TYPE(config)='OBJECT'),CHECK(target_key<>''),
INDEX node_module_bindings_target(module_code,binding_code,target_key,node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE module_schema_migrations (
module_code VARCHAR(120) NOT NULL,migration VARCHAR(255) NOT NULL,checksum CHAR(64) NOT NULL,
dirty BOOLEAN NOT NULL DEFAULT FALSE,applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),PRIMARY KEY(module_code,migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE module_package_inventory (
module_code VARCHAR(120) PRIMARY KEY,version VARCHAR(64) NOT NULL,package_sha256 CHAR(64) NOT NULL,
source VARCHAR(32) NOT NULL DEFAULT 'package',installed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE extension_sync_jobs (
singleton BOOLEAN PRIMARY KEY DEFAULT TRUE CHECK(singleton=TRUE),requested_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
INSERT INTO extension_sync_jobs(singleton) VALUES(TRUE);
-- cms:statement
INSERT INTO roles(code,name) VALUES('superadmin','Super Administrator'),('admin','Administrator'),('editor','Content Editor');
-- cms:statement
INSERT INTO permissions(code,name,global_only) VALUES('admin.access','Access administration panel',FALSE),('structure.manage','Manage site structure',FALSE),('layouts.manage','Manage layouts',FALSE),('users.manage','Manage users',FALSE),('modules.manage','Manage installed modules and package lifecycle',TRUE);
-- cms:statement
INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='superadmin' OR (r.code='admin' AND p.code IN ('admin.access','structure.manage','layouts.manage')) OR (r.code='editor' AND p.code IN ('admin.access','structure.manage'));
-- cms:statement
CREATE TRIGGER content_folders_tree_insert BEFORE INSERT ON content_folders FOR EACH ROW
BEGIN
DECLARE cursor_id BIGINT; DECLARE next_id BIGINT; DECLARE parent_depth INTEGER DEFAULT 0;
DECLARE subtree_depth INTEGER DEFAULT 1; DECLARE locked_site BIGINT;
SELECT id INTO locked_site FROM sites WHERE id=NEW.site_id FOR UPDATE;

SET cursor_id=NEW.parent_id;
WHILE cursor_id IS NOT NULL DO
 IF cursor_id=NEW.id OR parent_depth >= 63 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Folder cycle/depth exceeded'; END IF;
 SET parent_depth=parent_depth+1; SET next_id=NULL;
 SELECT parent_id INTO next_id FROM content_folders WHERE id=cursor_id AND site_id=NEW.site_id AND scope=NEW.scope;
 SET cursor_id=next_id;
END WHILE;
WITH RECURSIVE descendants AS (
 SELECT id,2 AS depth FROM content_folders WHERE parent_id=NEW.id
 UNION ALL SELECT f.id,d.depth+1 FROM content_folders f JOIN descendants d ON f.parent_id=d.id WHERE d.depth<64
) SELECT COALESCE(MAX(depth),1) INTO subtree_depth FROM descendants;
IF parent_depth+subtree_depth>64 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Folder subtree depth exceeded'; END IF;
END;
-- cms:statement
CREATE TRIGGER content_folders_tree_update BEFORE UPDATE ON content_folders FOR EACH ROW
BEGIN
DECLARE cursor_id BIGINT; DECLARE next_id BIGINT; DECLARE parent_depth INTEGER DEFAULT 0;
DECLARE subtree_depth INTEGER DEFAULT 1; DECLARE locked_site BIGINT;
SELECT id INTO locked_site FROM sites WHERE id=NEW.site_id FOR UPDATE;
IF OLD.site_id <> NEW.site_id OR OLD.scope <> NEW.scope THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Folder site/scope immutable'; END IF;
SET cursor_id=NEW.parent_id;
WHILE cursor_id IS NOT NULL DO
 IF cursor_id=NEW.id OR parent_depth >= 63 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Folder cycle/depth exceeded'; END IF;
 SET parent_depth=parent_depth+1; SET next_id=NULL;
 SELECT parent_id INTO next_id FROM content_folders WHERE id=cursor_id AND site_id=NEW.site_id AND scope=NEW.scope;
 SET cursor_id=next_id;
END WHILE;
WITH RECURSIVE descendants AS (
 SELECT id,2 AS depth FROM content_folders WHERE parent_id=NEW.id
 UNION ALL SELECT f.id,d.depth+1 FROM content_folders f JOIN descendants d ON f.parent_id=d.id WHERE d.depth<64
) SELECT COALESCE(MAX(depth),1) INTO subtree_depth FROM descendants;
IF parent_depth+subtree_depth>64 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Folder subtree depth exceeded'; END IF;
END;
-- cms:statement
CREATE TRIGGER nodes_slug_insert BEFORE INSERT ON nodes FOR EACH ROW
BEGIN
IF NOT ((NEW.parent_id IS NULL AND NEW.slug='') OR (NEW.parent_id IS NOT NULL AND NEW.slug REGEXP '^[a-z0-9][a-z0-9-]{0,254}$')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid node slug'; END IF;
IF NEW.id<>0 AND NEW.parent_id=NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Node cannot parent itself'; END IF;
END;
-- cms:statement
CREATE TRIGGER nodes_slug_update BEFORE UPDATE ON nodes FOR EACH ROW
BEGIN
IF NOT ((NEW.parent_id IS NULL AND NEW.slug='') OR (NEW.parent_id IS NOT NULL AND NEW.slug REGEXP '^[a-z0-9][a-z0-9-]{0,254}$')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid node slug'; END IF;
IF NEW.id<>0 AND NEW.parent_id=NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Node cannot parent itself'; END IF;
END;

-- cms:statement
CREATE TABLE update_commits (token CHAR(32) PRIMARY KEY, committed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)) ENGINE=InnoDB;
