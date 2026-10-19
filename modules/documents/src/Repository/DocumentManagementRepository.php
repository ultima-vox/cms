<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents\Repository;

use PDO;
use RuntimeException;
use Core\Content\FolderRepository;
use UltimaVox\Modules\Documents\Admin\DocumentListQuery;

final readonly class DocumentManagementRepository
{
    private const LIST_SELECT = <<<'SQL'
SELECT
    d.id,
    d.code,
    d.folder_id,
    d.name,
    d.is_active,
    d.created_at,
    d.updated_at,
    published.id AS published_version_id,
    published.version AS published_version,
    draft.id AS draft_version_id,
    draft.version AS draft_version
FROM documents d
LEFT JOIN document_versions published ON published.id = (
    SELECT id FROM document_versions
    WHERE document_id = d.id AND status = 'published'
    ORDER BY version DESC, id DESC LIMIT 1
)
LEFT JOIN document_versions draft ON draft.id = (
    SELECT id FROM document_versions
    WHERE document_id = d.id AND status = 'draft'
    ORDER BY version DESC, id DESC LIMIT 1
)
SQL;

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
        $statement = $this->db->prepare(self::LIST_SELECT . ' WHERE d.site_id = :site_id ORDER BY d.name, d.id');
        $statement->execute(['site_id' => $this->siteId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int, per_page: int} */
    public function page(DocumentListQuery $query): array
    {
        (new FolderRepository($this->db, $this->siteId, 'documents'))->assertFolder($query->folderId);
        $where = ['d.site_id = :site_id'];
        $params = ['site_id' => $this->siteId];
        if (!$query->allFolders) {
            if ($query->folderId === null) {
                $where[] = 'd.folder_id IS NULL';
            } else {
                $where[] = 'd.folder_id = :folder_id';
                $params['folder_id'] = $query->folderId;
            }
        }
        if ($query->search !== '') {
            // Search means a literal substring: %, _ and ! are not wildcards.
            $pattern = '%' . strtr($query->search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $where[] = "(LOWER(d.name) LIKE LOWER(:search_name) ESCAPE '!' OR LOWER(d.code) LIKE LOWER(:search_code) ESCAPE '!')";
            $params['search_name'] = $pattern;
            $params['search_code'] = $pattern;
        }
        if ($query->status !== '') {
            $status = $query->status === 'draft' ? 'draft' : 'published';
            $exists = "EXISTS (SELECT 1 FROM document_versions v WHERE v.document_id = d.id AND v.status = :version_status)";
            $where[] = ($query->status === 'unpublished' ? 'NOT ' : '') . $exists;
            $params['version_status'] = $status;
        }
        $condition = ' WHERE ' . implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(*) FROM documents d' . $condition);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $query->perPage));
        $page = min($query->page, $pages);
        $order = match ($query->sort) {
            'name_desc' => 'd.name DESC, d.id DESC',
            'code' => 'd.code, d.id',
            'updated' => 'd.updated_at DESC, d.id DESC',
            default => 'd.name, d.id',
        };
        $statement = $this->db->prepare(self::LIST_SELECT . $condition . ' ORDER BY ' . $order . ' LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $query->perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $query->perPage, PDO::PARAM_INT);
        $statement->execute();

        return ['rows' => $statement->fetchAll(PDO::FETCH_ASSOC), 'total' => $total,
            'page' => $page, 'pages' => $pages, 'per_page' => $query->perPage];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        if ($id < 1) {
            throw new RuntimeException('Document id must be positive.');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT id, site_id, folder_id, code, name, is_active, created_at, updated_at
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
            ORDER BY version DESC, id DESC
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

    public function move(int $id, ?int $folderId): void
    {
        (new \Core\Content\FolderRepository($this->db, $this->siteId, 'documents'))->assertFolder($folderId);
        $q = $this->db->prepare('UPDATE documents SET folder_id = :folder WHERE id = :id AND site_id = :site');
        $q->execute(['folder' => $folderId, 'id' => $id, 'site' => $this->siteId]);
        if ($q->rowCount() !== 1) throw new RuntimeException('Document was not found for the current site.');
    }

    private function assertOwnedDocument(int $documentId): void
    {
        if ($this->find($documentId) === null) {
            throw new RuntimeException('Document was not found for the current site.');
        }
    }
}
