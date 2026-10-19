-- MySQL/MariaDB legacy schema. PostgreSQL migrations remain unchanged.
CREATE TABLE infosystems (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,site_id BIGINT NOT NULL,name VARCHAR(255) NOT NULL,code VARCHAR(120) NOT NULL,description TEXT,
field_schema JSON NOT NULL DEFAULT ('[]'),is_active BOOLEAN NOT NULL DEFAULT TRUE,folder_id BIGINT NULL,
folder_scope VARCHAR(80) GENERATED ALWAYS AS ('infosystems') STORED,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),UNIQUE(site_id,code),
FOREIGN KEY(site_id) REFERENCES sites(id) ON DELETE RESTRICT,
FOREIGN KEY(site_id,folder_scope,folder_id) REFERENCES content_folders(site_id,scope,id) ON DELETE RESTRICT,
CHECK(JSON_TYPE(field_schema)='ARRAY')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE infosystem_groups (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,infosystem_id BIGINT NOT NULL,parent_id BIGINT NULL,name VARCHAR(255) NOT NULL,
slug VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,path VARCHAR(1024) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
description TEXT,is_active BOOLEAN NOT NULL DEFAULT TRUE,sorting INTEGER NOT NULL DEFAULT 0,
parent_key BIGINT GENERATED ALWAYS AS (COALESCE(parent_id,0)) STORED,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),UNIQUE(infosystem_id,id),
UNIQUE(infosystem_id,path),UNIQUE(infosystem_id,parent_key,slug),
FOREIGN KEY(infosystem_id) REFERENCES infosystems(id) ON DELETE CASCADE,
FOREIGN KEY(infosystem_id,parent_id) REFERENCES infosystem_groups(infosystem_id,id) ON DELETE RESTRICT,
CHECK(slug REGEXP '^[a-z0-9][a-z0-9-]*$'),CHECK(LEFT(path,1)='/')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE infosystem_items (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,infosystem_id BIGINT NOT NULL,group_id BIGINT NULL,name VARCHAR(255) NOT NULL,
slug VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,path VARCHAR(1024) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
description TEXT,content LONGTEXT NOT NULL DEFAULT (''),meta_description VARCHAR(320),status VARCHAR(20) NOT NULL DEFAULT 'draft',
is_active BOOLEAN NOT NULL DEFAULT TRUE,sorting INTEGER NOT NULL DEFAULT 0,properties JSON NOT NULL DEFAULT ('{}'),publish_at DATETIME(6),
group_key BIGINT GENERATED ALWAYS AS (COALESCE(group_id,0)) STORED,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),UNIQUE(infosystem_id,path),UNIQUE(infosystem_id,group_key,slug),
FOREIGN KEY(infosystem_id) REFERENCES infosystems(id) ON DELETE CASCADE,
FOREIGN KEY(infosystem_id,group_id) REFERENCES infosystem_groups(infosystem_id,id) ON DELETE RESTRICT,
CHECK(slug REGEXP '^[a-z0-9][a-z0-9-]*$'),CHECK(status IN ('draft','published','archived')),CHECK(JSON_TYPE(properties)='OBJECT'),
INDEX infosystem_items_publication(infosystem_id,is_active,status,publish_at),INDEX infosystem_items_sort(infosystem_id,group_id,sorting,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
