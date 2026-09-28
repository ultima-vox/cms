ALTER TABLE layouts
    ADD COLUMN description TEXT,
    ADD COLUMN is_system BOOLEAN NOT NULL DEFAULT FALSE;

CREATE INDEX idx_nodes_layout_id ON nodes(layout_id);

INSERT INTO layouts (name, template_path, description, is_system)
VALUES ('Основной макет', 'layouts/main.twig', 'Системный макет по умолчанию.', TRUE)
ON CONFLICT (template_path) DO UPDATE
SET is_system = TRUE,
    description = COALESCE(layouts.description, EXCLUDED.description),
    updated_at = CURRENT_TIMESTAMP;
