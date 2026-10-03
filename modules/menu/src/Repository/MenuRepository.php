<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu\Repository;

use PDO;
use RuntimeException;

final readonly class MenuRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActiveByCode(int $siteId, string $code): ?array
    {
        if ($siteId < 1) {
            throw new RuntimeException('Menu lookup requires a positive site id.');
        }

        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z][a-z0-9_-]{0,119}$/', $code)) {
            throw new RuntimeException('Menu code is invalid.');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, site_id, code, name
            FROM menus
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
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function findActiveLinkItems(int $menuId): array
    {
        if ($menuId < 1) {
            throw new RuntimeException('Menu item lookup requires a positive menu id.');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, menu_id, parent_id, label, url, sorting
            FROM menu_items
            WHERE menu_id = :menu_id
              AND kind = 'link'
              AND is_active = TRUE
            ORDER BY parent_id NULLS FIRST, sorting, id
            SQL
        );
        $statement->execute(['menu_id' => $menuId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }
}
