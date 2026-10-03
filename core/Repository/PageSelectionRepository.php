<?php

declare(strict_types=1);

namespace Core\Repository;

use JsonException;
use PDO;
use RuntimeException;

final readonly class PageSelectionRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @param array<string, mixed> $configuration */
    public function assign(
        int $nodeId,
        int $siteId,
        string $pageType,
        array $configuration,
    ): void {
        if ($nodeId < 1 || $siteId < 1) {
            throw new RuntimeException('Page selection requires positive node and site ids.');
        }

        $pageType = strtolower(trim($pageType));
        if (!preg_match('/^[a-z][a-z0-9._-]{0,127}$/', $pageType)) {
            throw new RuntimeException('Page selection type code is invalid.');
        }

        try {
            $encoded = json_encode(
                $configuration,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Page selection configuration is not JSON serializable.', 0, $exception);
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            UPDATE nodes
            SET page_type = :page_type,
                page_config = CAST(:page_config AS jsonb),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND site_id = :site_id
            SQL
        );
        $statement->execute([
            'id' => $nodeId,
            'site_id' => $siteId,
            'page_type' => $pageType,
            'page_config' => $encoded,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Page selection node was not found for the current site.');
        }
    }
}
