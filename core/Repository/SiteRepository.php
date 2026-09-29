<?php

declare(strict_types=1);

namespace Core\Repository;

use PDO;

final class SiteRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActiveByHost(string $host): ?array
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT
                s.id,
                s.code,
                s.name,
                s.settings,
                d.host,
                d.is_primary
            FROM site_domains d
            JOIN sites s ON s.id = d.site_id
            WHERE d.host = :host
              AND s.is_active = TRUE
            LIMIT 1
            SQL
        );
        $statement->execute(['host' => $host]);
        $row = $statement->fetch();

        return is_array($row) ? $this->normalize($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findActiveByCode(string $code): ?array
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, code, name, settings
            FROM sites
            WHERE code = :code
              AND is_active = TRUE
            LIMIT 1
            SQL
        );
        $statement->execute(['code' => $code]);
        $row = $statement->fetch();

        return is_array($row) ? $this->normalize($row) : null;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalize(array $row): array
    {
        if (isset($row['settings']) && is_string($row['settings'])) {
            $decoded = json_decode($row['settings'], true, flags: JSON_THROW_ON_ERROR);
            $row['settings'] = is_array($decoded) ? $decoded : [];
        }

        return $row;
    }
}
