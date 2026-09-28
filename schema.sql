CREATE TABLE layouts (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    template_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE infosystems (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE nodes (
    id SERIAL PRIMARY KEY,
    parent_id INT DEFAULT 0,
    layout_id INT REFERENCES layouts(id) ON DELETE SET NULL,
    infosystem_id INT REFERENCES infosystems(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL,
    path VARCHAR(255) NOT NULL,
    title VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    sorting INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE infosystem_items (
    id SERIAL PRIMARY KEY,
    infosystem_id INT REFERENCES infosystems(id) ON DELETE CASCADE,
    parent_id INT DEFAULT 0,
    is_group BOOLEAN DEFAULT FALSE,
    name VARCHAR(255) NOT NULL,
    path VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sorting INT DEFAULT 0,
    properties JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_nodes_path ON nodes(path);
CREATE INDEX idx_nodes_parent ON nodes(parent_id);
CREATE INDEX idx_items_infosystem ON infosystem_items(infosystem_id, parent_id);
CREATE INDEX idx_items_properties ON infosystem_items USING GIN (properties);
