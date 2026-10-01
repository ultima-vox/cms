<?php

declare(strict_types=1);

namespace Core\Repository;

use PDO;
use RuntimeException;

final class LayoutRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $statement = $this->db->query(
            <<<'SQL'
            SELECT
                l.id,
                l.parent_id,
                l.code,
                l.name,
                l.template_path,
                l.description,
                l.is_system,
                l.created_at,
                l.updated_at,
                COUNT(n.id)::int AS node_count
            FROM layouts l
            LEFT JOIN nodes n ON n.layout_id = l.id
            GROUP BY l.id
            ORDER BY l.name, l.id
            SQL
        );

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT
                l.id,
                l.parent_id,
                l.code,
                l.name,
                l.template_path,
                l.description,
                l.is_system,
                l.created_at,
                l.updated_at,
                COUNT(n.id)::int AS node_count
            FROM layouts l
            LEFT JOIN nodes n ON n.layout_id = l.id
            WHERE l.id = :id
            GROUP BY l.id
            LIMIT 1
            SQL
        );
        $statement->execute(['id' => $id]);
        $layout = $statement->fetch();

        return is_array($layout) ? $layout : null;
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $code)) {
            throw new RuntimeException('Layout code is invalid.');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT
                l.id,
                l.parent_id,
                l.code,
                l.name,
                l.template_path,
                l.description,
                l.is_system,
                l.created_at,
                l.updated_at,
                COUNT(n.id)::int AS node_count
            FROM layouts l
            LEFT JOIN nodes n ON n.layout_id = l.id
            WHERE l.code = :code
            GROUP BY l.id
            LIMIT 1
            SQL
        );
        $statement->execute(['code' => $code]);
        $layout = $statement->fetch();

        return is_array($layout) ? $layout : null;
    }

    public function defaultSystemId(): ?int
    {
        $value = $this->db->query(
            'SELECT id FROM layouts WHERE is_system = TRUE ORDER BY id LIMIT 1'
        )->fetchColumn();

        if ($value === false) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /** @return list<array<string, mixed>> */
    public function ancestry(int $id, int $maximumDepth): array
    {
        if ($id < 1) {
            throw new RuntimeException('Layout id must be positive.');
        }
        if ($maximumDepth < 1) {
            throw new RuntimeException('Layout hierarchy depth must be positive.');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            WITH RECURSIVE layout_chain AS (
                SELECT
                    l.id,
                    l.parent_id,
                    l.name,
                    l.template_path,
                    ARRAY[l.id]::bigint[] AS visited,
                    FALSE AS cycle,
                    1 AS depth
                FROM layouts l
                WHERE l.id = :id

                UNION ALL

                SELECT
                    parent.id,
                    parent.parent_id,
                    parent.name,
                    parent.template_path,
                    child.visited || parent.id,
                    parent.id = ANY(child.visited) AS cycle,
                    child.depth + 1
                FROM layouts parent
                JOIN layout_chain child ON parent.id = child.parent_id
                WHERE child.cycle = FALSE
                  AND child.depth <= :maximum_depth
            )
            SELECT id, parent_id, name, template_path, cycle, depth
            FROM layout_chain
            ORDER BY depth DESC
            SQL
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':maximum_depth', $maximumDepth, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function create(string $name, string $templatePath, ?string $description): int
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO layouts (code, name, template_path, description)
            VALUES (:code, :name, :template_path, :description)
            RETURNING id
            SQL
        );
        $statement->execute([
            'code' => $this->codeFromTemplatePath($templatePath),
            'name' => $name,
            'template_path' => $templatePath,
            'description' => $description,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function update(int $id, string $name, ?string $description): void
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            UPDATE layouts
            SET name = :name,
                description = :description,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
            SQL
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'description' => $description,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Макет не найден.');
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->db->prepare(
            'DELETE FROM layouts WHERE id = :id AND is_system = FALSE'
        );
        $statement->execute(['id' => $id]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Макет не найден или является системным.');
        }
    }

    public function isTemplatePathUsed(string $templatePath): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM layouts WHERE template_path = :template_path LIMIT 1'
        );
        $statement->execute(['template_path' => $templatePath]);

        return $statement->fetchColumn() !== false;
    }

    private function codeFromTemplatePath(string $templatePath): string
    {
        if (!preg_match(
            '#^layouts/([a-z0-9][a-z0-9_-]{0,79})\.(?:html\.php|php|twig)$#',
            trim($templatePath),
            $matches,
        )) {
            throw new RuntimeException('Layout template path cannot be converted to a stable code.');
        }

        return $matches[1];
    }
}
