<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu;

use PDO;
use RuntimeException;

final readonly class StructureNavigationSource implements NavigationSourceInterface
{
    private const MAX_DEPTH = 10;

    public function __construct(private PDO $db)
    {
    }

    public function resolve(NavigationSourceContext $context, array $configuration): array
    {
        foreach (array_keys($configuration) as $key) {
            if (!in_array($key, ['parent_path', 'depth'], true)) {
                throw new RuntimeException(sprintf(
                    'Unknown Structure navigation configuration key: %s.',
                    (string) $key,
                ));
            }
        }

        $parentPath = $configuration['parent_path'] ?? '/';
        if (!is_string($parentPath) || trim($parentPath) === '' || $parentPath[0] !== '/') {
            throw new RuntimeException('Structure navigation parent_path must be an absolute path.');
        }
        $parentPath = trim($parentPath);

        $depth = $configuration['depth'] ?? 1;
        if (is_string($depth) && ctype_digit($depth)) {
            $depth = (int) $depth;
        }
        if (!is_int($depth) || $depth < 1 || $depth > self::MAX_DEPTH) {
            throw new RuntimeException(sprintf(
                'Structure navigation depth must be in the range 1..%d.',
                self::MAX_DEPTH,
            ));
        }

        $parentStatement = $this->db->prepare(
            <<<'SQL'
            SELECT id
            FROM nodes
            WHERE site_id = :site_id
              AND path = :path
            LIMIT 1
            SQL
        );
        $parentStatement->execute([
            'site_id' => $context->site->id,
            'path' => $parentPath,
        ]);
        $parentId = $parentStatement->fetchColumn();
        if ($parentId === false) {
            throw new RuntimeException(sprintf(
                'Structure navigation parent "%s" was not found for the current site.',
                $parentPath,
            ));
        }
        $parentId = (int) $parentId;

        $statement = $this->db->prepare(
            <<<'SQL'
            WITH RECURSIVE tree AS (
                SELECT
                    n.id,
                    n.parent_id,
                    n.name,
                    n.path,
                    n.sorting,
                    1 AS depth,
                    ARRAY[n.id]::BIGINT[] AS ancestry
                FROM nodes n
                WHERE n.site_id = :site_id
                  AND n.parent_id = :parent_id
                  AND n.is_active = TRUE
                  AND n.status = 'published'
                  AND (n.publish_at IS NULL OR n.publish_at <= CURRENT_TIMESTAMP)

                UNION ALL

                SELECT
                    n.id,
                    n.parent_id,
                    n.name,
                    n.path,
                    n.sorting,
                    t.depth + 1,
                    t.ancestry || n.id
                FROM nodes n
                JOIN tree t ON n.parent_id = t.id
                WHERE n.site_id = :site_id
                  AND t.depth < :max_depth
                  AND n.is_active = TRUE
                  AND n.status = 'published'
                  AND (n.publish_at IS NULL OR n.publish_at <= CURRENT_TIMESTAMP)
                  AND NOT n.id = ANY(t.ancestry)
            )
            SELECT id, parent_id, name, path, sorting, depth
            FROM tree
            ORDER BY parent_id, sorting, name, id
            SQL
        );
        $statement->execute([
            'site_id' => $context->site->id,
            'parent_id' => $parentId,
            'max_depth' => $depth,
        ]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            $rows = [];
        }

        $context->renderContext->dependency('site:' . $context->site->id . ':structure');
        $context->renderContext->dependency('node:' . $parentId);
        $context->renderContext->dependency('site:' . $context->site->id . ':node:' . $parentId);
        foreach ($rows as $row) {
            $nodeId = (int) ($row['id'] ?? 0);
            if ($nodeId > 0) {
                $context->renderContext->dependency('node:' . $nodeId);
                $context->renderContext->dependency('site:' . $context->site->id . ':node:' . $nodeId);
            }
        }

        return $this->buildTree($rows, $parentId);
    }

    /** @param list<array<string, mixed>> $rows @return list<NavigationNode> */
    private function buildTree(array $rows, int $parentId): array
    {
        /** @var array<int, list<array<string, mixed>>> $children */
        $children = [];
        foreach ($rows as $row) {
            $rowParentId = isset($row['parent_id']) ? (int) $row['parent_id'] : 0;
            $children[$rowParentId][] = $row;
        }

        $walk = function (int $currentParentId) use (&$walk, $children): array {
            $nodes = [];
            foreach ($children[$currentParentId] ?? [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    throw new RuntimeException('Structure navigation node id is invalid.');
                }

                $nodes[] = new NavigationNode(
                    'structure:' . $id,
                    (string) ($row['name'] ?? ''),
                    (string) ($row['path'] ?? ''),
                    $walk($id),
                );
            }

            return $nodes;
        };

        return $walk($parentId);
    }
}
