<?php

declare(strict_types=1);

namespace Core\Repository;

use PDO;

final class LoginAttemptRepository
{
    private const MAX_IDENTITY_ATTEMPTS = 5;
    private const MAX_IP_ATTEMPTS = 25;

    public function __construct(private readonly PDO $db)
    {
    }

    public function isBlocked(string $email, string $ipAddress): bool
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT
                (
                    SELECT COUNT(*)
                    FROM login_attempts
                    WHERE lower(email) = lower(:email)
                      AND ip_address = :ip_address
                      AND attempted_at >= :since_identity
                ) AS identity_attempts,
                (
                    SELECT COUNT(*)
                    FROM login_attempts
                    WHERE ip_address = :ip_address
                      AND attempted_at >= :since_ip
                ) AS ip_attempts
            SQL
        );
        $statement->execute([
            'since_identity' => gmdate('Y-m-d H:i:s', time() - 900),
            'since_ip' => gmdate('Y-m-d H:i:s', time() - 900),
            'email' => $email,
            'ip_address' => $ipAddress,
        ]);

        $counts = $statement->fetch();

        if (!is_array($counts)) {
            return false;
        }

        return (int) $counts['identity_attempts'] >= self::MAX_IDENTITY_ATTEMPTS
            || (int) $counts['ip_attempts'] >= self::MAX_IP_ATTEMPTS;
    }

    public function recordFailure(string $email, string $ipAddress): void
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO login_attempts (email, ip_address)
            VALUES (:email, :ip_address)
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
              AND ip_address = :ip_address
            SQL
        );
        $statement->execute([
            'email' => $email,
            'ip_address' => $ipAddress,
        ]);
    }
}
