<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Sites;

use Core\Routing\Permalink;

use PDO;
use RuntimeException;
use Throwable;

final class SitesManagementRepository
{
    public function __construct(private readonly PDO $db, private readonly ?int $userId = null)
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

        return $this->userId === null ? (is_array($rows) ? $rows : []) : (new \Core\Site\SiteAccessRepository($this->db))->filter(is_array($rows) ? $rows : [], $this->userId);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        if ($this->userId !== null && !(new \Core\Site\SiteAccessRepository($this->db))->canAccess($this->userId, $id)) return null;
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
        if ($this->find($siteId) === null) return [];
        $statement = $this->db->prepare(
            'SELECT id, site_id, host, is_primary, created_at FROM site_domains WHERE site_id = :site_id ORDER BY is_primary DESC, host'
        );
        $statement->execute(['site_id' => $siteId]);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function create(string $name, string $code, string $permalinkStyle = 'clean'): int
    {
        if ($this->userId !== null && !(new \Core\Site\SiteAccessRepository($this->db))->isGlobal($this->userId)) throw new RuntimeException('Создавать сайты может только суперадминистратор.');
        new Permalink('/', $permalinkStyle);
        $statement = $this->db->prepare(
            'INSERT INTO sites (name, code, settings) VALUES (:name, :code, :settings) ' . \Core\Database\SqlDialect::returningId($this->db));
        $statement->execute([
            'name' => $name,
            'code' => $code,
            'settings' => json_encode(['permalink_style' => $permalinkStyle], JSON_THROW_ON_ERROR),
        ]);

        return \Core\Database\SqlDialect::insertedId($this->db, $statement);
    }

    public function update(int $id, string $name, bool $isActive, ?string $permalinkStyle = null): void
    {
        if ($permalinkStyle !== null) {
            new Permalink('/', $permalinkStyle);
        }
        $site = $this->find($id);
        if ($site === null) {
            throw new RuntimeException('Сайт не найден.');
        }
        if ((string) $site['code'] === 'default' && !$isActive) {
            throw new RuntimeException('Системный сайт default нельзя отключить.');
        }

        $statement = $this->db->prepare(
            'UPDATE sites SET name = :name, is_active = :is_active, settings = ' . (\Core\Database\SqlDialect::mysql($this->db) ? 'JSON_MERGE_PATCH(settings, :settings_delta)' : 'settings || CAST(:settings_delta AS jsonb)') . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'is_active' => (int) $isActive,
            'settings_delta' => $permalinkStyle === null
                ? '{}'
                : json_encode(['permalink_style' => $permalinkStyle], JSON_THROW_ON_ERROR),
        ]);
    }

    public function addDomain(int $siteId, string $host, bool $primary): int
    {
        if ($this->find($siteId) === null) {
            throw new RuntimeException('Сайт не найден.');
        }

        $owned = !$this->db->inTransaction();
        if ($owned) $this->db->beginTransaction();
        try {
            if ($primary) {
                $clear = $this->db->prepare('UPDATE site_domains SET is_primary = FALSE WHERE site_id = :site_id');
                $clear->execute(['site_id' => $siteId]);
            }

            $statement = $this->db->prepare(
                'INSERT INTO site_domains (site_id, host, is_primary) VALUES (:site_id, :host, :is_primary) ' . \Core\Database\SqlDialect::returningId($this->db));
            $statement->execute([
                'site_id' => $siteId,
                'host' => $host,
                'is_primary' => (int) $primary,
            ]);
            $id = \Core\Database\SqlDialect::insertedId($this->db, $statement);
            if ($owned) $this->db->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($owned && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function makePrimary(int $siteId, int $domainId): void
    {
        $owned = !$this->db->inTransaction();
        if ($owned) $this->db->beginTransaction();
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
            if ($owned) $this->db->commit();
        } catch (Throwable $exception) {
            if ($owned && $this->db->inTransaction()) {
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
