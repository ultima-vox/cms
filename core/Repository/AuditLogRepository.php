<?php

declare(strict_types=1);

namespace Core\Repository;

use PDO;

final class AuditLogRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @param array<string, mixed> $context */
    public function record(
        ?int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        array $context,
        ?string $ipAddress,
    ): void {
        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO audit_log (user_id, action, entity_type, entity_id, context, ip_address)
            VALUES (:user_id, :action, :entity_type, :entity_id, CAST(:context AS jsonb), CAST(:ip_address AS inet))
            SQL
        );
        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'context' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => $ipAddress,
        ]);
    }
}
