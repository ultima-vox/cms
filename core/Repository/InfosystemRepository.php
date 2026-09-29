<?php

declare(strict_types=1);

namespace Core\Repository;

use PDO;
use RuntimeException;

final class InfosystemRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActiveById(int $siteId, int $id): ?array
    {
        if ($siteId < 1 || $id < 1) {
            return null;
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, site_id, name, code, description, field_schema, is_active, created_at, updated_at
            FROM infosystems
            WHERE site_id = :site_id
              AND id = :id
              AND is_active = TRUE
            LIMIT 1
            SQL
        );
        $statement->execute([
            'site_id' => $siteId,
            'id' => $id,
        ]);
        $record = $statement->fetch();

        return is_array($record) ? $this->normalizeInfosystem($record) : null;
    }

    /** @return array<string, mixed>|null */
    public function findActiveByCode(int $siteId, string $code): ?array
    {
        if ($siteId < 1) {
            return null;
        }

        $code = strtolower(trim($code));
        if ($code === '') {
            return null;
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, site_id, name, code, description, field_schema, is_active, created_at, updated_at
            FROM infosystems
            WHERE site_id = :site_id
              AND code = :code
              AND is_active = TRUE
            LIMIT 1
            SQL
        );
        $statement->execute([
            'site_id' => $siteId,
            'code' => $code,
        ]);
        $record = $statement->fetch();

        return is_array($record) ? $this->normalizeInfosystem($record) : null;
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function findPublishedItems(
        int $siteId,
        int $infosystemId,
        int $limit = 100,
        int $offset = 0,
        array $filters = [],
    ): array {
        if ($siteId < 1 || $infosystemId < 1) {
            return [];
        }
        if ($limit < 1 || $limit > 500) {
            throw new RuntimeException('Limit должен быть в диапазоне 1..500.');
        }
        if ($offset < 0) {
            throw new RuntimeException('Offset не может быть отрицательным.');
        }

        $where = [
            'i.infosystem_id = :infosystem_id',
            's.site_id = :site_id',
            's.is_active = TRUE',
            'i.is_active = TRUE',
            "i.status = 'published'",
            '(i.publish_at IS NULL OR i.publish_at <= CURRENT_TIMESTAMP)',
            '(i.group_id IS NULL OR i.group_id IN (SELECT id FROM visible_groups))',
        ];
        $params = [
            'site_id' => $siteId,
            'infosystem_id' => $infosystemId,
        ];

        if ($filters !== []) {
            $where[] = 'i.properties @> CAST(:filters AS jsonb)';
            $params['filters'] = json_encode(
                $filters,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        }

        $sql = sprintf(
            <<<'SQL'
            WITH RECURSIVE visible_groups AS (
                SELECT id
                FROM infosystem_groups
                WHERE infosystem_id = :infosystem_id
                  AND parent_id IS NULL
                  AND is_active = TRUE

                UNION ALL

                SELECT child.id
                FROM infosystem_groups child
                JOIN visible_groups parent ON parent.id = child.parent_id
                WHERE child.infosystem_id = :infosystem_id
                  AND child.is_active = TRUE
            )
            SELECT
                i.id,
                i.infosystem_id,
                i.group_id,
                i.name,
                i.slug,
                i.path,
                i.description,
                i.content,
                i.meta_description,
                i.sorting,
                i.properties,
                i.created_at,
                i.updated_at,
                g.name AS group_name,
                g.path AS group_path
            FROM infosystem_items i
            JOIN infosystems s ON s.id = i.infosystem_id
            LEFT JOIN infosystem_groups g ON g.id = i.group_id
            WHERE %s
            ORDER BY i.sorting ASC, i.id ASC
            LIMIT :limit OFFSET :offset
            SQL,
            implode(' AND ', $where),
        );

        $statement = $this->db->prepare($sql);
        $statement->bindValue(':site_id', $siteId, PDO::PARAM_INT);
        $statement->bindValue(':infosystem_id', $infosystemId, PDO::PARAM_INT);
        if (isset($params['filters'])) {
            $statement->bindValue(':filters', $params['filters'], PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $items = $statement->fetchAll();

        foreach ($items as &$item) {
            if (isset($item['properties']) && is_string($item['properties'])) {
                $item['properties'] = json_decode($item['properties'], true, flags: JSON_THROW_ON_ERROR);
            }
        }
        unset($item);

        return $items;
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    private function normalizeInfosystem(array $record): array
    {
        if (isset($record['field_schema']) && is_string($record['field_schema'])) {
            $record['field_schema'] = json_decode(
                $record['field_schema'],
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        }

        return $record;
    }
}
