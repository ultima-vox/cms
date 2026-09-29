<?php

declare(strict_types=1);

namespace Core\Repository;

use PDO;
use RuntimeException;

final class NodeRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findPublishedByPath(int $siteId, string $path): ?array
    {
        if ($siteId < 1) {
            throw new RuntimeException('Site id must be positive.');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT
                n.id,
                n.site_id,
                n.parent_id,
                n.name,
                n.path,
                n.title,
                n.layout_id,
                n.infosystem_id,
                n.content,
                n.meta_description,
                l.template_path
            FROM nodes n
            LEFT JOIN layouts l ON l.id = n.layout_id
            WHERE n.site_id = :site_id
              AND n.path = :path
              AND n.is_active = TRUE
              AND n.status = 'published'
              AND (n.publish_at IS NULL OR n.publish_at <= CURRENT_TIMESTAMP)
            LIMIT 1
            SQL
        );
        $statement->execute([
            'site_id' => $siteId,
            'path' => $path,
        ]);

        $node = $statement->fetch();

        return is_array($node) ? $node : null;
    }
}
