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

    /** @return list<array<string, mixed>> */
    public function findPublishedItems(int $infosystemId, int $limit = 100, int $offset = 0): array
    {
        if ($limit < 1 || $limit > 500) {
            throw new RuntimeException('Limit должен быть в диапазоне 1..500.');
        }

        if ($offset < 0) {
            throw new RuntimeException('Offset не может быть отрицательным.');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT
                id,
                infosystem_id,
                parent_id,
                is_group,
                name,
                path,
                sorting,
                properties,
                created_at,
                updated_at
            FROM infosystem_items
            WHERE infosystem_id = :infosystem_id
              AND is_active = TRUE
              AND status = 'published'
              AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)
            ORDER BY sorting ASC, id ASC
            LIMIT :limit OFFSET :offset
            SQL
        );
        $statement->bindValue(':infosystem_id', $infosystemId, PDO::PARAM_INT);
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
}
