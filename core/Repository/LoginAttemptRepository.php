<?php

declare(strict_types=1);

namespace Core\Repository;

use PDO;

final class LoginAttemptRepository
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public function __construct(private readonly PDO $db)
    {
    }

    public function isBlocked(string $email, string $ipAddress): bool
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT COUNT(*)
            FROM login_attempts
            WHERE lower(email) = lower(:email)
              AND ip_address = CAST(:ip_address AS inet)
              AND attempted_at >= CURRENT_TIMESTAMP - INTERVAL '15 minutes'
            SQL
        );
        $statement->execute([
            'email' => $email,
            'ip_address' => $ipAddress,
        ]);

        return (int) $statement->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public function recordFailure(string $email, string $ipAddress): void
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO login_attempts (email, ip_address)
            VALUES (:email, CAST(:ip_address AS inet))
            SQL
        );
        $statement->execute([
            'email' => $email,
            'ip_address' => $ipAddress,
        ]);
    }

    public function clear(string $email, string $ipAddress): void
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            DELETE FROM login_attempts
            WHERE lower(email) = lower(:email)
              AND ip_address = CAST(:ip_address AS inet)
            SQL
        );
        $statement->execute([
            'email' => $email,
            'ip_address' => $ipAddress,
        ]);
    }
}
