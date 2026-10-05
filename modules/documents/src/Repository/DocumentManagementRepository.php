<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents\Repository;

use PDO;
use RuntimeException;

final readonly class DocumentManagementRepository
{
    public function __construct(
        private PDO $db,
        private int $siteId,
    ) {
        if ($siteId < 1) {
            throw new RuntimeException('Documents management requires a positive site id.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT
                d.id,
                d.code,
                d.name,
                d.is_active,
                d.created_at,
                d.updated_at,
                published.id AS published_version_id,
                published.version AS published_version,
                draft.id AS draft_version_id,
                draft.version AS draft_version
            FROM documents d
            LEFT JOIN LATERAL (
                SELECT id, version
                FROM document_versions
                WHERE document_id = d.id
                  AND status = 'published'
                ORDER BY version DESC, id DESC
                LIMIT 1
            ) published ON TRUE
            LEFT JOIN LATERAL (
                SELECT id, version
                FROM document_versions
                WHERE document_id = d.id
                  AND status = 'draft'
                ORDER BY version DESC, id DESC
                LIMIT 1
            ) draft ON TRUE
            WHERE d.site_id = :site_id
            ORDER BY d.name, d.id
            SQL
        );
        $statement->execute(['site_id' => $this->siteId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        if ($id < 1) {
            throw new RuntimeException('Document id must be positive.');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, site_id, code, name, is_active, created_at, updated_at
            FROM documents
            WHERE id = :id
              AND site_id = :site_id
            LIMIT 1
            SQL
        );
        $statement->execute([
            'id' => $id,
            'site_id' => $this->siteId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function versions(int $documentId): array
    {
        $this->assertOwnedDocument($documentId);

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, document_id, version, content, status, created_at, published_at
            FROM document_versions
            WHERE document_id = :document_id
            ORDER BY version DESC, id DESC
            SQL
        );
        $statement->execute(['document_id' => $documentId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function editableVersion(int $documentId): ?array
    {
        $this->assertOwnedDocument($documentId);

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, document_id, version, content, status, created_at, published_at
            FROM document_versions
            WHERE document_id = :document_id
            ORDER BY
                CASE status WHEN 'draft' THEN 0 WHEN 'published' THEN 1 ELSE 2 END,
                version DESC,
                id DESC
            LIMIT 1
            SQL
        );
        $statement->execute(['document_id' => $documentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function updateName(int $id, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Document name is required.');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            UPDATE documents
            SET name = :name,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND site_id = :site_id
            SQL
        );
        $statement->execute([
            'id' => $id,
            'site_id' => $this->siteId,
            'name' => $name,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Document was not found for the current site.');
        }
    }

    private function assertOwnedDocument(int $documentId): void
    {
        if ($this->find($documentId) === null) {
            throw new RuntimeException('Document was not found for the current site.');
        }
    }
}
