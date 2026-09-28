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

    public function create(string $name, string $templatePath, ?string $description): int
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO layouts (name, template_path, description)
            VALUES (:name, :template_path, :description)
            RETURNING id
            SQL
        );
        $statement->execute([
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
}
