<?php

declare(strict_types=1);

namespace Core\Repository;

use PDO;
use RuntimeException;
use Throwable;

final class StructureRepository
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
            SELECT
                n.id,
                n.site_id,
                n.parent_id,
                n.name,
                n.slug,
                n.path,
                n.title,
                n.layout_id,
                n.status,
                n.is_active,
                n.sorting,
                n.publish_at,
                l.name AS layout_name
            FROM nodes n
            LEFT JOIN layouts l ON l.id = n.layout_id
            WHERE n.site_id = :site_id
            ORDER BY n.parent_id NULLS FIRST, n.sorting, n.name, n.id
            SQL
        );
        $statement->execute(['site_id' => $this->siteId]);

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM nodes WHERE id = :id AND site_id = :site_id LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
            'site_id' => $this->siteId,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array{id:int,name:string,template_path:string}> */
    public function layouts(): array
    {
        $rows = $this->db->query('SELECT id, name, template_path FROM layouts ORDER BY name, id')->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $pageConfig
     */
    public function create(
        array $data,
        string $pageType,
        array $pageConfig = [],
    ): int {
        $parentId = $data['parent_id'];
        $path = $this->buildPath($parentId, (string) $data['slug']);

        $pageType = strtolower(trim($pageType));
        if (!preg_match('/^[a-z][a-z0-9._-]{0,127}$/', $pageType)) {
            throw new RuntimeException('Page type code is invalid.');
        }

        $pageConfigJson = json_encode(
            (object) $pageConfig,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO nodes (
                site_id, parent_id, layout_id, name, slug, path, title,
                meta_description, status, is_active, sorting, publish_at,
                page_type, page_config
            ) VALUES (
                :site_id, :parent_id, :layout_id, :name, :slug, :path, :title,
                :meta_description, :status, CAST(:is_active AS BOOLEAN), :sorting, :publish_at,
                :page_type, CAST(:page_config AS jsonb)
            )
            RETURNING id
            SQL
        );

        $parameters = [
            'site_id' => $this->siteId,
            'parent_id' => $parentId,
            'layout_id' => $data['layout_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'path' => $path,
            'title' => $data['title'],
            'meta_description' => $data['meta_description'],
            'status' => $data['status'],
            'is_active' => (bool) $data['is_active'] ? 'true' : 'false',
            'sorting' => $data['sorting'],
            'publish_at' => $data['publish_at'],
        ];

        $parameters['page_type'] = $pageType;
        $parameters['page_config'] = $pageConfigJson;

        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $current = $this->find($id);
        if ($current === null) {
            throw new RuntimeException('Узел не найден.');
        }

        $parentId = $data['parent_id'];
        if ($parentId !== null && $this->isDescendant($parentId, $id)) {
            throw new RuntimeException('Нельзя переместить узел внутрь собственного поддерева.');
        }

        $oldPath = (string) $current['path'];
        $newPath = $this->buildPath($parentId, (string) $data['slug']);

        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                <<<'SQL'
                UPDATE nodes SET
                    parent_id = :parent_id,
                    layout_id = :layout_id,
                    name = :name,
                    slug = :slug,
                    path = :path,
                    title = :title,
                    meta_description = :meta_description,
                    status = :status,
                    is_active = CAST(:is_active AS BOOLEAN),
                    sorting = :sorting,
                    publish_at = :publish_at,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND site_id = :site_id
                SQL
            );
            $statement->execute([
                'id' => $id,
                'site_id' => $this->siteId,
                'parent_id' => $parentId,
                'layout_id' => $data['layout_id'],
                'name' => $data['name'],
                'slug' => $data['slug'],
                'path' => $newPath,
                'title' => $data['title'],
                'meta_description' => $data['meta_description'],
                'status' => $data['status'],
                'is_active' => (bool) $data['is_active'] ? 'true' : 'false',
                'sorting' => $data['sorting'],
                'publish_at' => $data['publish_at'],
            ]);

            if ($oldPath !== $newPath) {
                $this->rewriteDescendantPaths($id, $oldPath, $newPath);
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->db->prepare('DELETE FROM nodes WHERE id = :id AND site_id = :site_id');
        $statement->execute([
            'id' => $id,
            'site_id' => $this->siteId,
        ]);
    }

    /** @param list<array{id:int,parent_id:int|null,sorting:int}> $items */
    public function reorder(array $items): void
    {
        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                $node = $this->find($item['id']);
                if ($node === null) {
                    throw new RuntimeException('Один из сортируемых узлов не найден.');
                }

                $parentId = $item['parent_id'];
                if ($parentId !== null && $this->isDescendant($parentId, $item['id'])) {
                    throw new RuntimeException('Некорректное перемещение узла.');
                }

                $oldPath = (string) $node['path'];
                $newPath = $this->buildPath($parentId, (string) $node['slug']);

                $statement = $this->db->prepare(
                    'UPDATE nodes SET parent_id = :parent_id, sorting = :sorting, path = :path, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND site_id = :site_id'
                );
                $statement->execute([
                    'id' => $item['id'],
                    'site_id' => $this->siteId,
                    'parent_id' => $parentId,
                    'sorting' => $item['sorting'],
                    'path' => $newPath,
                ]);

                if ($oldPath !== $newPath) {
                    $this->rewriteDescendantPaths($item['id'], $oldPath, $newPath);
                }
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function buildPath(?int $parentId, string $slug): string
    {
        if ($parentId === null) {
            if ($slug !== '') {
                throw new RuntimeException('Корневой узел должен иметь пустой slug.');
            }
            return '/';
        }

        $parent = $this->find($parentId);
        if ($parent === null) {
            throw new RuntimeException('Родительский узел не найден.');
        }

        $parentPath = rtrim((string) $parent['path'], '/');
        return ($parentPath === '' ? '' : $parentPath) . '/' . $slug;
    }

    private function rewriteDescendantPaths(int $id, string $oldPath, string $newPath): void
    {
        if ($oldPath === '/') {
            $oldPrefix = '/';
            $newPrefix = rtrim($newPath, '/') . '/';
        } else {
            $oldPrefix = rtrim($oldPath, '/') . '/';
            $newPrefix = rtrim($newPath, '/') . '/';
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            UPDATE nodes
            SET path = :new_prefix || substring(path FROM :offset),
                updated_at = CURRENT_TIMESTAMP
            WHERE site_id = :site_id
              AND id <> :id
              AND path LIKE :pattern
            SQL
        );
        $statement->execute([
            'site_id' => $this->siteId,
            'id' => $id,
            'new_prefix' => $newPrefix,
            'offset' => strlen($oldPrefix) + 1,
            'pattern' => $oldPrefix . '%',
        ]);
    }

    private function isDescendant(int $candidateParentId, int $nodeId): bool
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            WITH RECURSIVE descendants AS (
                SELECT id
                FROM nodes
                WHERE parent_id = :node_id
                  AND site_id = :site_id

                UNION ALL

                SELECT n.id
                FROM nodes n
                JOIN descendants d ON n.parent_id = d.id
                WHERE n.site_id = :site_id
            )
            SELECT 1 FROM descendants WHERE id = :candidate_parent_id LIMIT 1
            SQL
        );
        $statement->execute([
            'site_id' => $this->siteId,
            'node_id' => $nodeId,
            'candidate_parent_id' => $candidateParentId,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
