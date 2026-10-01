ALTER TABLE layouts
    ADD COLUMN code VARCHAR(80);

WITH derived AS (
    SELECT
        id,
        lower(
            regexp_replace(
                regexp_replace(template_path, '^layouts/', ''),
                '(\.html\.php|\.php|\.twig)$',
                ''
            )
        ) AS base_code
    FROM layouts
),
normalized AS (
    SELECT
        id,
        CASE
            WHEN base_code ~ '^[a-z0-9][a-z0-9_-]{0,79}$' THEN base_code
            ELSE 'layout-' || id::text
        END AS base_code
    FROM derived
),
ranked AS (
    SELECT
        id,
        base_code,
        row_number() OVER (PARTITION BY base_code ORDER BY id) AS occurrence
    FROM normalized
)
UPDATE layouts l
SET code = CASE
    WHEN r.occurrence = 1 THEN r.base_code
    ELSE left(r.base_code, 58) || '-' || l.id::text
END
FROM ranked r
WHERE r.id = l.id;

ALTER TABLE layouts
    ALTER COLUMN code SET NOT NULL,
    ADD CONSTRAINT layouts_code_format CHECK (code ~ '^[a-z0-9][a-z0-9_-]{0,79}$');

CREATE UNIQUE INDEX idx_layouts_code ON layouts (code);
