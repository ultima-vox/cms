-- MySQL/MariaDB legacy schema. PostgreSQL migrations remain unchanged.
CREATE TABLE menus (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT NOT NULL, FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    code VARCHAR(120) NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT menus_code_format CHECK (code REGEXP '^[a-z][a-z0-9_-]{0,119}$'),
    CONSTRAINT menus_site_code_unique UNIQUE (site_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- cms:statement
CREATE TABLE menu_items (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    menu_id BIGINT NOT NULL, FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    parent_id BIGINT,
    kind VARCHAR(20) NOT NULL DEFAULT 'link'
        CHECK (kind IN ('link', 'source')),
    label VARCHAR(255),
    url TEXT,
    source_code VARCHAR(128),
    source_config JSON NOT NULL DEFAULT ('{}'),
    sorting INTEGER NOT NULL DEFAULT 0 CHECK (sorting >= 0),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT menu_items_menu_id_id_unique UNIQUE (menu_id, id),
    CONSTRAINT menu_items_parent_same_menu
        FOREIGN KEY (menu_id, parent_id)
        REFERENCES menu_items(menu_id, id)
        ON DELETE CASCADE,
    CONSTRAINT menu_items_source_config_object CHECK (JSON_TYPE(source_config) = 'OBJECT'),
    CONSTRAINT menu_items_source_code_format CHECK (
        source_code IS NULL OR source_code REGEXP '^[a-z][a-z0-9._-]{0,127}$'
    ),
    CONSTRAINT menu_items_kind_payload CHECK (
        (
            kind = 'link'
            AND label IS NOT NULL
            AND TRIM(label) <> ''
            AND url IS NOT NULL
            AND TRIM(url) <> ''
            AND source_code IS NULL
        )
        OR
        (
            kind = 'source'
            AND source_code IS NOT NULL
            AND url IS NULL
        )
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
