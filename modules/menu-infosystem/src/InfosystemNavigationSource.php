<?php

declare(strict_types=1);

namespace UltimaVox\Modules\MenuInfosystem;

use PDO;
use RuntimeException;
use UltimaVox\Modules\Infosystem\Repository\InfosystemRepository;
use UltimaVox\Modules\Menu\NavigationNode;
use UltimaVox\Modules\Menu\NavigationSourceContext;
use UltimaVox\Modules\Menu\NavigationSourceInterface;

final readonly class InfosystemNavigationSource implements NavigationSourceInterface
{
    private const MAX_DEPTH = 10;
    private const MAX_ITEMS = 500;

    public function __construct(
        private PDO $db,
        private InfosystemRepository $repository,
    ) {
    }

    public function resolve(NavigationSourceContext $context, array $configuration): array
    {
        $config = $this->normalizeConfiguration($configuration);
        $infosystem = $this->repository->findActiveByCode(
            $context->site->id,
            $config['infosystem'],
        );
        if ($infosystem === null) {
            throw new RuntimeException(sprintf(
                'Infosystem "%s" was not found for the current site.',
                $config['infosystem'],
            ));
        }

        $infosystemId = (int) $infosystem['id'];
        $groups = $config['groups']
            ? $this->findGroups($infosystemId, $config['depth'])
            : [];
        $items = $config['items']
            ? $this->repository->findPublishedItems(
                $context->site->id,
                $infosystemId,
                $config['limit'],
            )
            : [];

        $context->renderContext->dependency('infosystem:' . $infosystemId);
        $context->renderContext->dependency(
            'site:' . $context->site->id . ':infosystem:' . $infosystemId,
        );

        foreach ($groups as $group) {
            $groupId = (int) ($group['id'] ?? 0);
            if ($groupId > 0) {
                $context->renderContext->dependency('infosystem_group:' . $groupId);
                $context->renderContext->dependency(
                    'site:' . $context->site->id . ':infosystem_group:' . $groupId,
                );
            }
        }
        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId > 0) {
                $context->renderContext->dependency('infosystem_item:' . $itemId);
                $context->renderContext->dependency(
                    'site:' . $context->site->id . ':infosystem_item:' . $itemId,
                );
            }
        }

        if (!$config['groups']) {
            return $this->flatItemNodes($items, $config['base_path']);
        }

        return $this->groupedNodes(
            $groups,
            $config['items'] ? $items : [],
            $config['base_path'],
        );
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array{infosystem:string,base_path:string,groups:bool,items:bool,depth:int,limit:int}
     */
    private function normalizeConfiguration(array $configuration): array
    {
        $allowed = ['infosystem', 'base_path', 'groups', 'items', 'depth', 'limit'];
        foreach (array_keys($configuration) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new RuntimeException(sprintf(
                    'Unknown Infosystem navigation configuration key: %s.',
                    (string) $key,
                ));
            }
        }

        $code = $configuration['infosystem'] ?? null;
        if (!is_string($code)) {
            throw new RuntimeException('Infosystem navigation requires an infosystem code.');
        }
        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z][a-z0-9_-]{0,119}$/', $code)) {
            throw new RuntimeException('Infosystem navigation code is invalid.');
        }

        $basePath = $configuration['base_path'] ?? '/';
        if (!is_string($basePath) || trim($basePath) === '' || $basePath[0] !== '/') {
            throw new RuntimeException('Infosystem navigation base_path must be an absolute path.');
        }
        $basePath = $this->normalizeBasePath($basePath);

        $groups = $configuration['groups'] ?? true;
        $items = $configuration['items'] ?? false;
        if (!is_bool($groups) || !is_bool($items)) {
            throw new RuntimeException('Infosystem navigation groups/items flags must be boolean.');
        }
        if (!$groups && !$items) {
            throw new RuntimeException('Infosystem navigation must enable groups, items, or both.');
        }

        $depth = $configuration['depth'] ?? 2;
        if (is_string($depth) && ctype_digit($depth)) {
            $depth = (int) $depth;
        }
        if (!is_int($depth) || $depth < 1 || $depth > self::MAX_DEPTH) {
            throw new RuntimeException(sprintf(
                'Infosystem navigation depth must be in the range 1..%d.',
                self::MAX_DEPTH,
            ));
        }

        $limit = $configuration['limit'] ?? 100;
        if (is_string($limit) && ctype_digit($limit)) {
            $limit = (int) $limit;
        }
        if (!is_int($limit) || $limit < 1 || $limit > self::MAX_ITEMS) {
            throw new RuntimeException(sprintf(
                'Infosystem navigation limit must be in the range 1..%d.',
                self::MAX_ITEMS,
            ));
        }

        return [
            'infosystem' => $code,
            'base_path' => $basePath,
            'groups' => $groups,
            'items' => $items,
            'depth' => $depth,
            'limit' => $limit,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function findGroups(int $infosystemId, int $depth): array
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            WITH RECURSIVE visible_groups AS (
                SELECT
                    g.id,
                    g.parent_id,
                    g.name,
                    g.path,
                    g.sorting,
                    1 AS depth,
                    ARRAY[g.id]::BIGINT[] AS ancestry
                FROM infosystem_groups g
                WHERE g.infosystem_id = :infosystem_id
                  AND g.parent_id IS NULL
                  AND g.is_active = TRUE

                UNION ALL

                SELECT
                    g.id,
                    g.parent_id,
                    g.name,
                    g.path,
                    g.sorting,
                    parent.depth + 1,
                    parent.ancestry || g.id
                FROM infosystem_groups g
                JOIN visible_groups parent ON parent.id = g.parent_id
                WHERE g.infosystem_id = :infosystem_id
                  AND g.is_active = TRUE
                  AND parent.depth < :max_depth
                  AND NOT g.id = ANY(parent.ancestry)
            )
            SELECT id, parent_id, name, path, sorting, depth
            FROM visible_groups
            ORDER BY parent_id NULLS FIRST, sorting, name, id
            SQL
        );
        $statement->bindValue(':infosystem_id', $infosystemId, PDO::PARAM_INT);
        $statement->bindValue(':max_depth', $depth, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param list<array<string, mixed>> $groups
     * @param list<array<string, mixed>> $items
     * @return list<NavigationNode>
     */
    private function groupedNodes(array $groups, array $items, string $basePath): array
    {
        /** @var array<int, array<string, mixed>> $groupsById */
        $groupsById = [];
        /** @var array<int, list<array<string, mixed>>> $groupChildren */
        $groupChildren = [];
        foreach ($groups as $group) {
            $id = (int) ($group['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $groupsById[$id] = $group;
            $parentId = $group['parent_id'] === null ? 0 : (int) $group['parent_id'];
            $groupChildren[$parentId][] = $group;
        }

        /** @var array<int, list<array<string, mixed>>> $itemsByGroup */
        $itemsByGroup = [];
        foreach ($items as $item) {
            $groupId = $item['group_id'] === null ? 0 : (int) $item['group_id'];
            if ($groupId !== 0 && !isset($groupsById[$groupId])) {
                continue;
            }
            $itemsByGroup[$groupId][] = $item;
        }

        $walk = function (int $parentId) use (&$walk, $groupChildren, $itemsByGroup, $basePath): array {
            $entries = [];

            foreach ($groupChildren[$parentId] ?? [] as $group) {
                $entries[] = [
                    'sorting' => (int) ($group['sorting'] ?? 0),
                    'kind' => 0,
                    'id' => (int) $group['id'],
                    'node' => new NavigationNode(
                        'infosystem-group:' . (int) $group['id'],
                        (string) ($group['name'] ?? ''),
                        $this->publicPath($basePath, (string) ($group['path'] ?? '')),
                        $walk((int) $group['id']),
                    ),
                ];
            }

            foreach ($itemsByGroup[$parentId] ?? [] as $item) {
                $entries[] = [
                    'sorting' => (int) ($item['sorting'] ?? 0),
                    'kind' => 1,
                    'id' => (int) $item['id'],
                    'node' => $this->itemNode($item, $basePath),
                ];
            }

            usort(
                $entries,
                static fn (array $left, array $right): int =>
                    [$left['sorting'], $left['kind'], $left['id']]
                    <=> [$right['sorting'], $right['kind'], $right['id']],
            );

            return array_map(
                static fn (array $entry): NavigationNode => $entry['node'],
                $entries,
            );
        };

        return $walk(0);
    }

    /** @param list<array<string, mixed>> $items @return list<NavigationNode> */
    private function flatItemNodes(array $items, string $basePath): array
    {
        return array_map(
            fn (array $item): NavigationNode => $this->itemNode($item, $basePath),
            $items,
        );
    }

    /** @param array<string, mixed> $item */
    private function itemNode(array $item, string $basePath): NavigationNode
    {
        $id = (int) ($item['id'] ?? 0);
        if ($id < 1) {
            throw new RuntimeException('Infosystem navigation item id is invalid.');
        }

        return new NavigationNode(
            'infosystem-item:' . $id,
            (string) ($item['name'] ?? ''),
            $this->publicPath($basePath, (string) ($item['path'] ?? '')),
        );
    }

    private function normalizeBasePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : $path . '/';
    }

    private function publicPath(string $basePath, string $entityPath): string
    {
        $entityPath = trim($entityPath);
        if ($entityPath === '' || $entityPath[0] !== '/') {
            throw new RuntimeException('Infosystem navigation entity path must be absolute.');
        }

        $relative = trim($entityPath, '/');
        if ($relative === '') {
            return $basePath;
        }

        return $basePath === '/'
            ? '/' . $relative . '/'
            : $basePath . $relative . '/';
    }
}
