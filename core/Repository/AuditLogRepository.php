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

    /**
     * @return list<array{
     *     id:int,
     *     action:string,
     *     entity_type:?string,
     *     entity_id:?int,
     *     display_name:?string,
     *     created_at:string
     * }>
     */
    public function recent(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT
                audit_log.id,
                audit_log.action,
                audit_log.entity_type,
                audit_log.entity_id,
                users.display_name,
                audit_log.created_at
            FROM audit_log
            LEFT JOIN users ON users.id = audit_log.user_id
            ORDER BY audit_log.created_at DESC, audit_log.id DESC
            LIMIT :limit
            SQL
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'action' => (string) $row['action'],
                'entity_type' => $row['entity_type'] !== null ? (string) $row['entity_type'] : null,
                'entity_id' => $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
                'display_name' => $row['display_name'] !== null ? (string) $row['display_name'] : null,
                'created_at' => (string) $row['created_at'],
            ],
            $rows,
        );
    }
}
