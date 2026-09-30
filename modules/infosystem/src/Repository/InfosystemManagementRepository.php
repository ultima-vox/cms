<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem\Repository;

use PDO;
use RuntimeException;
use Throwable;

final class InfosystemManagementRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly int $siteId = 1,
    ) {
        if ($this->siteId < 1) {
            throw new RuntimeException('Site id must be positive.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT i.*,
                   COUNT(DISTINCT g.id) AS group_count,
                   COUNT(DISTINCT item.id) AS item_count,
                   COUNT(DISTINCT n.id) AS node_count
            FROM infosystems i
            LEFT JOIN infosystem_groups g ON g.infosystem_id = i.id
            LEFT JOIN infosystem_items item ON item.infosystem_id = i.id
            LEFT JOIN node_module_bindings b
                ON b.module_code = 'infosystem'
               AND b.binding_code = 'primary'
               AND b.target_key = i.code
            LEFT JOIN nodes n ON n.id = b.node_id AND n.site_id = i.site_id
            WHERE i.site_id = :site_id
            GROUP BY i.id
            ORDER BY i.name, i.id
            SQL
        );
        $statement->execute(['site_id' => $this->siteId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT i.*,
                   (SELECT COUNT(*) FROM infosystem_groups g WHERE g.infosystem_id = i.id) AS group_count,
                   (SELECT COUNT(*) FROM infosystem_items item WHERE item.infosystem_id = i.id) AS item_count,
                   (
                       SELECT COUNT(*)
                       FROM node_module_bindings b
                       JOIN nodes n ON n.id = b.node_id
                       WHERE b.module_code = 'infosystem'
                         AND b.binding_code = 'primary'
                         AND b.target_key = i.code
                         AND n.site_id = i.site_id
                   ) AS node_count
            FROM infosystems i
            WHERE i.id = :id
              AND i.site_id = :site_id
            LIMIT 1
            SQL
        );
        $statement->execute([
            'id' => $id,
            'site_id' => $this->siteId,
        ]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (is_string($row['field_schema'] ?? null)) {
            $row['field_schema'] = json_decode($row['field_schema'], true, flags: JSON_THROW_ON_ERROR);
        }

        return $row;
    }

    public function create(string $name, string $code, ?string $description, array $fieldSchema, bool $isActive): int
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO infosystems (site_id, name, code, description, field_schema, is_active)
            VALUES (:site_id, :name, :code, :description, CAST(:field_schema AS jsonb), :is_active)
            RETURNING id
            SQL
        );
        $statement->execute([
            'site_id' => $this->siteId,
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'field_schema' => json_encode($fieldSchema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => $isActive,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function update(int $id, string $name, ?string $description, array $fieldSchema, bool $isActive): void
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            UPDATE infosystems
            SET name = :name,
                description = :description,
                field_schema = CAST(:field_schema AS jsonb),
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND site_id = :site_id
            SQL
        );
        $statement->execute([
            'id' => $id,
            'site_id' => $this->siteId,
            'name' => $name,
            'description' => $description,
            'field_schema' => json_encode($fieldSchema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => $isActive,
        ]);
    }

    public function delete(int $id): void
    {
        $system = $this->find($id);
        if ($system === null) {
            return;
        }
        if ((int) $system['node_count'] > 0) {
            throw new RuntimeException('Инфосистема используется в структуре сайта.');
        }

        $statement = $this->db->prepare('DELETE FROM infosystems WHERE id = :id AND site_id = :site_id');
        $statement->execute([
            'id' => $id,
            'site_id' => $this->siteId,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function groups(int $infosystemId): array
    {
        if (!$this->belongsToSite($infosystemId)) {
            return [];
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT g.*,
                   (SELECT COUNT(*) FROM infosystem_groups child WHERE child.parent_id = g.id) AS child_count,
                   (SELECT COUNT(*) FROM infosystem_items i WHERE i.group_id = g.id) AS item_count
            FROM infosystem_groups g
            WHERE g.infosystem_id = :infosystem_id
            ORDER BY g.path, g.sorting, g.id
            SQL
        );
        $statement->execute(['infosystem_id' => $infosystemId]);
        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findGroup(int $infosystemId, int $id): ?array
    {
        if (!$this->belongsToSite($infosystemId)) {
            return null;
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT g.*,
                   (SELECT COUNT(*) FROM infosystem_groups child WHERE child.parent_id = g.id) AS child_count,
                   (SELECT COUNT(*) FROM infosystem_items i WHERE i.group_id = g.id) AS item_count
            FROM infosystem_groups g
            WHERE g.infosystem_id = :infosystem_id AND g.id = :id
            LIMIT 1
            SQL
        );
        $statement->execute(['infosystem_id' => $infosystemId, 'id' => $id]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function createGroup(
        int $infosystemId,
        ?int $parentId,
        string $name,
        string $slug,
        ?string $description,
        int $sorting = 0,
        bool $isActive = true,
    ): int {
        $this->assertBelongsToSite($infosystemId);
        $path = $this->groupPath($infosystemId, $parentId, $slug);
        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO infosystem_groups
                (infosystem_id, parent_id, name, slug, path, description, sorting, is_active)
            VALUES
                (:infosystem_id, :parent_id, :name, :slug, :path, :description, :sorting, :is_active)
            RETURNING id
            SQL
        );
        $statement->execute([
            'infosystem_id' => $infosystemId,
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'path' => $path,
            'description' => $description,
            'sorting' => $sorting,
            'is_active' => $isActive,
        ]);
        return (int) $statement->fetchColumn();
    }

    public function updateGroup(
        int $infosystemId,
        int $id,
        ?int $parentId,
        string $name,
        string $slug,
        ?string $description,
        int $sorting,
        bool $isActive,
    ): void {
        $this->assertBelongsToSite($infosystemId);
        $group = $this->findGroup($infosystemId, $id);
        if ($group === null) {
            throw new RuntimeException('Группа не найдена.');
        }
        if ($parentId === $id || ($parentId !== null && $this->isGroupDescendant($id, $parentId))) {
            throw new RuntimeException('Нельзя переместить группу внутрь собственного поддерева.');
        }

        $oldPath = (string) $group['path'];
        $newPath = $this->groupPath($infosystemId, $parentId, $slug);

        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                <<<'SQL'
                UPDATE infosystem_groups
                SET parent_id = :parent_id,
                    name = :name,
                    slug = :slug,
                    path = :path,
                    description = :description,
                    sorting = :sorting,
                    is_active = :is_active,
                    updated_at = CURRENT_TIMESTAMP
                WHERE infosystem_id = :infosystem_id AND id = :id
                SQL
            );
            $statement->execute([
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => $slug,
                'path' => $newPath,
                'description' => $description,
                'sorting' => $sorting,
                'is_active' => $isActive,
                'infosystem_id' => $infosystemId,
                'id' => $id,
            ]);

            if ($oldPath !== $newPath) {
                $groupUpdate = $this->db->prepare(
                    <<<'SQL'
                    UPDATE infosystem_groups
                    SET path = :new_path || substring(path from :start_position),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE infosystem_id = :infosystem_id
                      AND path LIKE :prefix
                    SQL
                );
                $groupUpdate->execute([
                    'new_path' => $newPath,
                    'start_position' => strlen($oldPath) + 1,
                    'infosystem_id' => $infosystemId,
                    'prefix' => $oldPath . '/%',
                ]);

                $itemUpdate = $this->db->prepare(
                    <<<'SQL'
                    UPDATE infosystem_items
                    SET path = :new_path || substring(path from :start_position),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE infosystem_id = :infosystem_id
                      AND path LIKE :prefix
                    SQL
                );
                $itemUpdate->execute([
                    'new_path' => $newPath,
                    'start_position' => strlen($oldPath) + 1,
                    'infosystem_id' => $infosystemId,
                    'prefix' => $oldPath . '/%',
                ]);
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteGroup(int $infosystemId, int $id): void
    {
        $this->assertBelongsToSite($infosystemId);
        $group = $this->findGroup($infosystemId, $id);
        if ($group === null) {
            return;
        }

        if ((int) $group['child_count'] > 0 || (int) $group['item_count'] > 0) {
            throw new RuntimeException('Нельзя удалить непустую группу.');
        }

        $statement = $this->db->prepare(
            'DELETE FROM infosystem_groups WHERE infosystem_id = :infosystem_id AND id = :id'
        );
        $statement->execute(['infosystem_id' => $infosystemId, 'id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function items(int $infosystemId, int $limit = 200, int $offset = 0): array
    {
        $this->assertBelongsToSite($infosystemId);
        if ($limit < 1 || $limit > 500) {
            throw new RuntimeException('Limit должен быть в диапазоне 1..500.');
        }
        if ($offset < 0) {
            throw new RuntimeException('Offset не может быть отрицательным.');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT i.*, g.name AS group_name
            FROM infosystem_items i
            LEFT JOIN infosystem_groups g ON g.id = i.group_id
            WHERE i.infosystem_id = :infosystem_id
            ORDER BY i.sorting, i.id
            LIMIT :limit OFFSET :offset
            SQL
        );
        $statement->bindValue(':infosystem_id', $infosystemId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $items = $statement->fetchAll();
        foreach ($items as &$item) {
            if (is_string($item['properties'] ?? null)) {
                $item['properties'] = json_decode($item['properties'], true, flags: JSON_THROW_ON_ERROR);
            }
        }
        unset($item);

        return $items;
    }

    /** @return array<string, mixed>|null */
    public function findItem(int $infosystemId, int $id): ?array
    {
        if (!$this->belongsToSite($infosystemId)) {
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT * FROM infosystem_items WHERE infosystem_id = :infosystem_id AND id = :id LIMIT 1'
        );
        $statement->execute(['infosystem_id' => $infosystemId, 'id' => $id]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }
        if (is_string($row['properties'] ?? null)) {
            $row['properties'] = json_decode($row['properties'], true, flags: JSON_THROW_ON_ERROR);
        }

        return $row;
    }

    /** @param array<string, mixed> $data */
    public function saveItem(?int $id, int $infosystemId, ?int $groupId, array $data): int
    {
        $this->assertBelongsToSite($infosystemId);
        $path = $this->itemPath($infosystemId, $groupId, (string) $data['slug']);
        $properties = json_encode(
            $data['properties'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        if ($id === null) {
            $statement = $this->db->prepare(
                <<<'SQL'
                INSERT INTO infosystem_items
                    (infosystem_id, group_id, name, slug, path, description, content, meta_description,
                     status, is_active, sorting, properties, publish_at)
                VALUES
                    (:infosystem_id, :group_id, :name, :slug, :path, :description, :content, :meta_description,
                     :status, :is_active, :sorting, CAST(:properties AS jsonb), :publish_at)
                RETURNING id
                SQL
            );
        } else {
            $statement = $this->db->prepare(
                <<<'SQL'
                UPDATE infosystem_items
                SET group_id = :group_id,
                    name = :name,
                    slug = :slug,
                    path = :path,
                    description = :description,
                    content = :content,
                    meta_description = :meta_description,
                    status = :status,
                    is_active = :is_active,
                    sorting = :sorting,
                    properties = CAST(:properties AS jsonb),
                    publish_at = :publish_at,
                    updated_at = CURRENT_TIMESTAMP
                WHERE infosystem_id = :infosystem_id AND id = :id
                RETURNING id
                SQL
            );
        }

        $params = [
            'infosystem_id' => $infosystemId,
            'group_id' => $groupId,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'path' => $path,
            'description' => $data['description'],
            'content' => $data['content'],
            'meta_description' => $data['meta_description'],
            'status' => $data['status'],
            'is_active' => $data['is_active'],
            'sorting' => $data['sorting'],
            'properties' => $properties,
            'publish_at' => $data['publish_at'],
        ];
        if ($id !== null) {
            $params['id'] = $id;
        }

        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function deleteItem(int $infosystemId, int $id): void
    {
        $this->assertBelongsToSite($infosystemId);
        $statement = $this->db->prepare(
            'DELETE FROM infosystem_items WHERE infosystem_id = :infosystem_id AND id = :id'
        );
        $statement->execute(['infosystem_id' => $infosystemId, 'id' => $id]);
    }

    private function groupPath(int $infosystemId, ?int $parentId, string $slug): string
    {
        if ($parentId === null) {
            return '/' . $slug;
        }

        $parent = $this->findGroup($infosystemId, $parentId);
        if ($parent === null) {
            throw new RuntimeException('Родительская группа не найдена.');
        }

        return rtrim((string) $parent['path'], '/') . '/' . $slug;
    }

    private function itemPath(int $infosystemId, ?int $groupId, string $slug): string
    {
        if ($groupId === null) {
            return '/' . $slug;
        }

        $group = $this->findGroup($infosystemId, $groupId);
        if ($group === null) {
            throw new RuntimeException('Группа элемента не найдена.');
        }

        return rtrim((string) $group['path'], '/') . '/' . $slug;
    }

    private function isGroupDescendant(int $ancestorId, int $candidateId): bool
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            WITH RECURSIVE descendants AS (
                SELECT id FROM infosystem_groups WHERE parent_id = :ancestor
                UNION ALL
                SELECT g.id
                FROM infosystem_groups g
                JOIN descendants d ON g.parent_id = d.id
            )
            SELECT 1 FROM descendants WHERE id = :candidate LIMIT 1
            SQL
        );
        $statement->execute(['ancestor' => $ancestorId, 'candidate' => $candidateId]);

        return $statement->fetchColumn() !== false;
    }

    private function belongsToSite(int $infosystemId): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM infosystems WHERE id = :id AND site_id = :site_id LIMIT 1'
        );
        $statement->execute([
            'id' => $infosystemId,
            'site_id' => $this->siteId,
        ]);
        return $statement->fetchColumn() !== false;
    }

    private function assertBelongsToSite(int $infosystemId): void
    {
        if (!$this->belongsToSite($infosystemId)) {
            throw new RuntimeException('Инфосистема не найдена для текущего сайта.');
        }
    }
}
