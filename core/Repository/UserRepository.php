<?php

declare(strict_types=1);

namespace Core\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActiveByEmail(string $email): ?array
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, email, password_hash, display_name, is_active
            FROM users
            WHERE lower(email) = lower(:email)
              AND is_active = TRUE
            LIMIT 1
            SQL
        );
        $statement->execute(['email' => $email]);

        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /** @return array<string, mixed>|null */
    public function findActiveById(int $id): ?array
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, email, display_name, is_active
            FROM users
            WHERE id = :id
              AND is_active = TRUE
            LIMIT 1
            SQL
        );
        $statement->execute(['id' => $id]);

        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function touchLastLogin(int $id): void
    {
        $statement = $this->db->prepare(
            'UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    public function hasPermission(int $userId, string $permission): bool
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT 1
            FROM user_roles ur
            JOIN role_permissions rp ON rp.role_id = ur.role_id
            JOIN permissions p ON p.id = rp.permission_id
            WHERE ur.user_id = :user_id
              AND p.code = :permission
            LIMIT 1
            SQL
        );
        $statement->execute([
            'user_id' => $userId,
            'permission' => $permission,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
