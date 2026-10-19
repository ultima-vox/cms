<?php

declare(strict_types=1);
namespace Core\Site;

use PDO;
use RuntimeException;
use Throwable;

final readonly class SiteAccessRepository
{
    public function __construct(private PDO $db) {}
    public function isGlobal(int $userId): bool
    {
        $q = $this->db->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :user AND r.code = 'superadmin'");
        $q->execute(['user' => $userId]);
        return $q->fetchColumn() !== false;
    }
    public function canAccess(int $userId, int $siteId): bool
    {
        if ($this->isGlobal($userId)) return true;
        $q = $this->db->prepare('SELECT 1 FROM user_sites WHERE user_id = :user AND site_id = :site');
        $q->execute(['user' => $userId, 'site' => $siteId]);
        return $q->fetchColumn() !== false;
    }
    public function filter(array $sites, int $userId): array
    {
        if ($this->isGlobal($userId)) return $sites;
        $q = $this->db->prepare('SELECT site_id FROM user_sites WHERE user_id = :user'); $q->execute(['user' => $userId]);
        $allowed = array_fill_keys(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)), true);
        return array_values(array_filter($sites, static fn ($site) => isset($allowed[is_array($site) ? (int) $site['id'] : $site->id])));
    }
    public function users(int $siteId): array
    {
        $q = $this->db->prepare("SELECT u.id, u.email, u.display_name, us.user_id IS NOT NULL AS granted, EXISTS(SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.code = 'superadmin') AS global_access FROM users u LEFT JOIN user_sites us ON us.user_id = u.id AND us.site_id = :site WHERE u.is_active = TRUE ORDER BY u.display_name, u.id");
        $q->execute(['site' => $siteId]); return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function assign(int $siteId, array $userIds): void
    {
        $ids = [];
        foreach ($userIds as $id) {
            if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $id)) throw new RuntimeException('Некорректный пользователь.');
            $ids[(int) $id] = true;
        }
        $owned = !$this->db->inTransaction();
        if ($owned) $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('site-access:' || CAST(:site AS text)))"); $lock->execute(['site' => $siteId]);
            $q = $this->db->prepare('DELETE FROM user_sites WHERE site_id = :site'); $q->execute(['site' => $siteId]);
            $q = $this->db->prepare('INSERT INTO user_sites(site_id, user_id) SELECT :site, id FROM users WHERE id = :user AND is_active = TRUE');
            foreach (array_keys($ids) as $id) {
                $q->execute(['site' => $siteId, 'user' => $id]);
                if ($q->rowCount() !== 1) throw new RuntimeException('Пользователь не найден или отключён.');
            }
            if ($owned) $this->db->commit();
        } catch (Throwable $e) { if ($owned && $this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
