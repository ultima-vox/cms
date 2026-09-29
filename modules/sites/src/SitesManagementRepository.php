<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Sites;

use PDO;
use RuntimeException;
use Throwable;

final class SitesManagementRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $rows = $this->db->query(
            <<<'SQL'
            SELECT s.*,
                   d.host AS primary_host,
                   (SELECT COUNT(*) FROM site_domains sd WHERE sd.site_id = s.id) AS domain_count,
                   (SELECT COUNT(*) FROM nodes n WHERE n.site_id = s.id) AS node_count,
                   (SELECT COUNT(*) FROM infosystems i WHERE i.site_id = s.id) AS infosystem_count
            FROM sites s
            LEFT JOIN site_domains d ON d.site_id = s.id AND d.is_primary = TRUE
            ORDER BY s.name, s.id
            SQL
        )->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM sites WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }
        if (is_string($row['settings'] ?? null)) {
            $row['settings'] = json_decode($row['settings'], true, flags: JSON_THROW_ON_ERROR);
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function domains(int $siteId): array
    {
        $statement = $this->db->prepare(
            'SELECT id, site_id, host, is_primary, created_at FROM site_domains WHERE site_id = :site_id ORDER BY is_primary DESC, host'
        );
        $statement->execute(['site_id' => $siteId]);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function create(string $name, string $code): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO sites (name, code) VALUES (:name, :code) RETURNING id'
        );
        $statement->execute(['name' => $name, 'code' => $code]);

        return (int) $statement->fetchColumn();
    }

    public function update(int $id, string $name, bool $isActive): void
    {
        $site = $this->find($id);
        if ($site === null) {
            throw new RuntimeException('Сайт не найден.');
        }
        if ((string) $site['code'] === 'default' && !$isActive) {
            throw new RuntimeException('Системный сайт default нельзя отключить.');
        }

        $statement = $this->db->prepare(
            'UPDATE sites SET name = :name, is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'is_active' => $isActive,
        ]);
    }

    public function addDomain(int $siteId, string $host, bool $primary): int
    {
        if ($this->find($siteId) === null) {
            throw new RuntimeException('Сайт не найден.');
        }

        $this->db->beginTransaction();
        try {
            if ($primary) {
                $clear = $this->db->prepare('UPDATE site_domains SET is_primary = FALSE WHERE site_id = :site_id');
                $clear->execute(['site_id' => $siteId]);
            }

            $statement = $this->db->prepare(
                'INSERT INTO site_domains (site_id, host, is_primary) VALUES (:site_id, :host, :is_primary) RETURNING id'
            );
            $statement->execute([
                'site_id' => $siteId,
                'host' => $host,
                'is_primary' => $primary,
            ]);
            $id = (int) $statement->fetchColumn();
            $this->db->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function makePrimary(int $siteId, int $domainId): void
    {
        $this->db->beginTransaction();
        try {
            $belongs = $this->db->prepare(
                'SELECT 1 FROM site_domains WHERE id = :id AND site_id = :site_id LIMIT 1'
            );
            $belongs->execute(['id' => $domainId, 'site_id' => $siteId]);
            if ($belongs->fetchColumn() === false) {
                throw new RuntimeException('Домен не найден для этого сайта.');
            }

            $clear = $this->db->prepare('UPDATE site_domains SET is_primary = FALSE WHERE site_id = :site_id');
            $clear->execute(['site_id' => $siteId]);
            $set = $this->db->prepare('UPDATE site_domains SET is_primary = TRUE WHERE id = :id AND site_id = :site_id');
            $set->execute(['id' => $domainId, 'site_id' => $siteId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteDomain(int $siteId, int $domainId): void
    {
        $statement = $this->db->prepare(
            'DELETE FROM site_domains WHERE id = :id AND site_id = :site_id'
        );
        $statement->execute(['id' => $domainId, 'site_id' => $siteId]);
    }
}
