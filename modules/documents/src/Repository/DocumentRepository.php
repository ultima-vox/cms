<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents\Repository;

use PDO;
use RuntimeException;
use Throwable;

final class DocumentRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $siteId, string $code, string $name): int
    {
        $this->assertSiteId($siteId);
        $code = $this->normalizeCode($code);
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Document name is required.');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO documents (site_id, code, name)
            VALUES (:site_id, :code, :name)
            RETURNING id
            SQL
        );
        $statement->execute([
            'site_id' => $siteId,
            'code' => $code,
            'name' => $name,
        ]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findActiveByCode(int $siteId, string $code): ?array
    {
        $this->assertSiteId($siteId);
        $code = $this->normalizeCode($code);

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, site_id, code, name, is_active, created_at, updated_at
            FROM documents
            WHERE site_id = :site_id
              AND code = :code
              AND is_active = TRUE
            LIMIT 1
            SQL
        );
        $statement->execute([
            'site_id' => $siteId,
            'code' => $code,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function currentPublishedVersion(int $documentId): ?array
    {
        $this->assertDocumentId($documentId);

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, document_id, version, content, status, created_at, published_at
            FROM document_versions
            WHERE document_id = :document_id
              AND status = 'published'
            LIMIT 1
            SQL
        );
        $statement->execute(['document_id' => $documentId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function createDraftVersion(int $documentId, string $content): int
    {
        $this->assertDocumentId($documentId);

        return $this->transaction(function () use ($documentId, $content): int {
            $this->lockDocument($documentId);

            $statement = $this->db->prepare(
                <<<'SQL'
                INSERT INTO document_versions (document_id, version, content, status)
                SELECT
                    :document_id,
                    COALESCE(MAX(version), 0) + 1,
                    :content,
                    'draft'
                FROM document_versions
                WHERE document_id = :document_id
                RETURNING id
                SQL
            );
            $statement->execute([
                'document_id' => $documentId,
                'content' => $content,
            ]);

            return (int) $statement->fetchColumn();
        });
    }

    public function publishVersion(int $documentId, int $versionId): void
    {
        $this->assertDocumentId($documentId);
        if ($versionId < 1) {
            throw new RuntimeException('Document version id must be positive.');
        }

        $this->transaction(function () use ($documentId, $versionId): void {
            $this->lockDocument($documentId);

            $target = $this->db->prepare(
                <<<'SQL'
                SELECT id, status
                FROM document_versions
                WHERE id = :id
                  AND document_id = :document_id
                FOR UPDATE
                SQL
            );
            $target->execute([
                'id' => $versionId,
                'document_id' => $documentId,
            ]);
            $row = $target->fetch();
            if (!is_array($row)) {
                throw new RuntimeException('Document version was not found.');
            }

            if (($row['status'] ?? null) === 'published') {
                return;
            }
            if (($row['status'] ?? null) !== 'draft') {
                throw new RuntimeException('Only a draft document version can be published.');
            }

            $archive = $this->db->prepare(
                <<<'SQL'
                UPDATE document_versions
                SET status = 'archived'
                WHERE document_id = :document_id
                  AND id <> :target_id
                  AND status IN ('published', 'draft')
                SQL
            );
            $archive->execute([
                'document_id' => $documentId,
                'target_id' => $versionId,
            ]);

            $publish = $this->db->prepare(
                <<<'SQL'
                UPDATE document_versions
                SET status = 'published',
                    published_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND document_id = :document_id
                  AND status = 'draft'
                SQL
            );
            $publish->execute([
                'id' => $versionId,
                'document_id' => $documentId,
            ]);
            if ($publish->rowCount() !== 1) {
                throw new RuntimeException('Document version could not be published.');
            }

            $touch = $this->db->prepare(
                'UPDATE documents SET updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            );
            $touch->execute(['id' => $documentId]);
        });
    }

    private function lockDocument(int $documentId): void
    {
        $statement = $this->db->prepare('SELECT id FROM documents WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $documentId]);
        if ($statement->fetchColumn() === false) {
            throw new RuntimeException('Document was not found.');
        }
    }

    private function assertSiteId(int $siteId): void
    {
        if ($siteId < 1) {
            throw new RuntimeException('Site id must be positive.');
        }
    }

    private function assertDocumentId(int $documentId): void
    {
        if ($documentId < 1) {
            throw new RuntimeException('Document id must be positive.');
        }
    }

    private function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z][a-z0-9_-]{0,119}$/', $code)) {
            throw new RuntimeException('Document code is invalid.');
        }

        return $code;
    }

    /** @template T @param callable():T $callback @return T */
    private function transaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();
            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
