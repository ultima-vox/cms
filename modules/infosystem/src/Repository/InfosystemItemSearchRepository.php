<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem\Repository;

use PDO;
use RuntimeException;

final class InfosystemItemSearchRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string, scalar> $propertyFilters
     * @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function search(
        int $infosystemId,
        int $page,
        int $perPage,
        ?string $query = null,
        ?int $groupId = null,
        ?string $status = null,
        array $propertyFilters = [],
    ): array {
        if ($page < 1) {
            throw new RuntimeException('Page должен быть больше нуля.');
        }
        if (!in_array($perPage, [25, 50, 100, 200], true)) {
            throw new RuntimeException('Недопустимый размер страницы.');
        }
        if ($status !== null && !in_array($status, ['draft', 'published', 'archived'], true)) {
            throw new RuntimeException('Некорректный статус.');
        }

        $where = ['i.infosystem_id = :infosystem_id'];
        $params = ['infosystem_id' => $infosystemId];

        if ($query !== null && $query !== '') {
            $where[] = "(i.name ILIKE :query ESCAPE E'\\\\' OR i.slug ILIKE :query ESCAPE E'\\\\' OR i.path ILIKE :query ESCAPE E'\\\\')";
            $params['query'] = '%' . $this->escapeLike($query) . '%';
        }
        if ($groupId !== null) {
            $where[] = 'i.group_id = :group_id';
            $params['group_id'] = $groupId;
        }
        if ($status !== null) {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }
        if ($propertyFilters !== []) {
            $where[] = 'i.properties @> CAST(:property_filters AS jsonb)';
            $params['property_filters'] = json_encode(
                $propertyFilters,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        }

        $whereSql = implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(*) FROM infosystem_items i WHERE ' . $whereSql);
        foreach ($params as $name => $value) {
            $count->bindValue(
                ':' . $name,
                $value,
                $name === 'infosystem_id' || $name === 'group_id' ? PDO::PARAM_INT : PDO::PARAM_STR,
            );
        }
        $count->execute();
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $sql = <<<'SQL'
            SELECT i.id, i.name, i.slug, i.path, i.status, i.is_active, i.sorting,
                   i.properties, i.publish_at, i.updated_at,
                   g.id AS group_id, g.name AS group_name
            FROM infosystem_items i
            LEFT JOIN infosystem_groups g ON g.id = i.group_id
            WHERE %s
            ORDER BY i.sorting ASC, i.id ASC
            LIMIT :limit OFFSET :offset
            SQL;
        $statement = $this->db->prepare(sprintf($sql, $whereSql));
        foreach ($params as $name => $value) {
            $statement->bindValue(
                ':' . $name,
                $value,
                $name === 'infosystem_id' || $name === 'group_id' ? PDO::PARAM_INT : PDO::PARAM_STR,
            );
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $items = $statement->fetchAll();
        foreach ($items as &$item) {
            if (is_string($item['properties'] ?? null)) {
                $item['properties'] = json_decode($item['properties'], true, flags: JSON_THROW_ON_ERROR);
            }
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    private function escapeLike(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '%' => '\\%',
            '_' => '\\_',
        ]);
    }
}
