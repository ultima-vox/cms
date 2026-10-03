CREATE TABLE menus (
    id BIGSERIAL PRIMARY KEY,
    site_id BIGINT NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    code VARCHAR(120) NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT menus_code_format CHECK (code ~ '^[a-z][a-z0-9_-]{0,119}$'),
    CONSTRAINT menus_site_code_unique UNIQUE (site_id, code)
);

CREATE TABLE menu_items (
    id BIGSERIAL PRIMARY KEY,
    menu_id BIGINT NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
    parent_id BIGINT,
    kind VARCHAR(20) NOT NULL DEFAULT 'link'
        CHECK (kind IN ('link', 'source')),
    label VARCHAR(255),
    url TEXT,
    source_code VARCHAR(128),
    source_config JSONB NOT NULL DEFAULT '{}'::jsonb,
    sorting INTEGER NOT NULL DEFAULT 0 CHECK (sorting >= 0),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT menu_items_menu_id_id_unique UNIQUE (menu_id, id),
    CONSTRAINT menu_items_parent_same_menu
        FOREIGN KEY (menu_id, parent_id)
        REFERENCES menu_items(menu_id, id)
        ON DELETE CASCADE,
    CONSTRAINT menu_items_source_config_object CHECK (jsonb_typeof(source_config) = 'object'),
    CONSTRAINT menu_items_source_code_format CHECK (
        source_code IS NULL OR source_code ~ '^[a-z][a-z0-9._-]{0,127}$'
    ),
    CONSTRAINT menu_items_kind_payload CHECK (
        (
            kind = 'link'
            AND label IS NOT NULL
            AND BTRIM(label) <> ''
            AND url IS NOT NULL
            AND BTRIM(url) <> ''
            AND source_code IS NULL
        )
        OR
        (
            kind = 'source'
            AND source_code IS NOT NULL
            AND url IS NULL
        )
    )
);

CREATE INDEX idx_menus_site_active
    ON menus (site_id, is_active, code, id);

CREATE INDEX idx_menu_items_tree
    ON menu_items (menu_id, parent_id, is_active, sorting, id);

CREATE INDEX idx_menu_items_source
    ON menu_items (menu_id, source_code, id)
    WHERE kind = 'source';
