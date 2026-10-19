-- MySQL/MariaDB legacy schema. PostgreSQL migrations remain unchanged.
CREATE TABLE documents (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,site_id BIGINT NOT NULL,code VARCHAR(120) NOT NULL,name VARCHAR(255) NOT NULL,is_active BOOLEAN NOT NULL DEFAULT TRUE,
folder_id BIGINT NULL,folder_scope VARCHAR(80) GENERATED ALWAYS AS ('documents') STORED,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),UNIQUE(site_id,code),
FOREIGN KEY(site_id) REFERENCES sites(id) ON DELETE CASCADE,
FOREIGN KEY(site_id,folder_scope,folder_id) REFERENCES content_folders(site_id,scope,id) ON DELETE RESTRICT,
CHECK(code REGEXP '^[a-z][a-z0-9_-]{0,119}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE document_versions (
id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,document_id BIGINT NOT NULL,version INTEGER NOT NULL,content LONGTEXT NOT NULL DEFAULT (''),
status VARCHAR(20) NOT NULL DEFAULT 'draft',created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),published_at DATETIME(6),
published_document BIGINT GENERATED ALWAYS AS (CASE WHEN status='published' THEN document_id ELSE NULL END) STORED UNIQUE,
FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE CASCADE,UNIQUE(document_id,version),
CHECK(version>0),CHECK(status IN ('draft','published','archived')),CHECK(status<>'published' OR published_at IS NOT NULL),
INDEX document_versions_status(document_id,status,version,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
