<?php

declare(strict_types=1);
namespace Core\Repository;

use Core\Content\FolderRepository;
use PDO;
use RuntimeException;

final readonly class LayoutRepository
{
    public function __construct(private PDO $db, private int $siteId = 1)
    {
        if ($siteId < 1) throw new RuntimeException('Layout site must be positive.');
    }
    public function forSite(int $siteId): self { return new self($this->db, $siteId); }
    public function all(): array
    {
        $q = $this->db->prepare('SELECT l.*, (SELECT COUNT(*) FROM nodes n WHERE n.layout_id=l.id AND n.site_id=l.site_id) AS node_count FROM layouts l WHERE l.site_id = :site ORDER BY l.name, l.id');
        $q->execute(['site' => $this->siteId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function find(int $id): ?array
    {
        $q = $this->db->prepare('SELECT l.*, (SELECT COUNT(*) FROM nodes n WHERE n.layout_id=l.id AND n.site_id=l.site_id) AS node_count FROM layouts l WHERE l.site_id = :site AND l.id = :id');
        $q->execute(['site' => $this->siteId, 'id' => $id]);
        return ($row = $q->fetch(PDO::FETCH_ASSOC)) !== false ? $row : null;
    }
    public function findByCode(string $code): ?array
    {
        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/D', $code)) throw new RuntimeException('Layout code is invalid.');
        $q = $this->db->prepare('SELECT id FROM layouts WHERE site_id = :site AND code = :code');
        $q->execute(['site' => $this->siteId, 'code' => $code]);
        return ($id = $q->fetchColumn()) !== false ? $this->find((int) $id) : null;
    }
    public function defaultSystemId(): ?int
    {
        $q = $this->db->prepare('SELECT id FROM layouts WHERE site_id = :site AND is_system = TRUE ORDER BY id LIMIT 1');
        $q->execute(['site' => $this->siteId]);
        return ($id = $q->fetchColumn()) !== false ? (int) $id : null;
    }
    public function ancestry(int $id, int $maximumDepth): array
    {
        if ($id < 1 || $maximumDepth < 1) throw new RuntimeException('Invalid layout hierarchy request.');
        if (\Core\Database\SqlDialect::mysql($this->db)) {
            $q = $this->db->prepare(<<<'SQL'
                WITH RECURSIVE layout_chain AS (
                    SELECT l.id,l.parent_id,l.name,l.template_path,l.site_id,
                           CAST(CONCAT(',',l.id,',') AS CHAR(4096)) AS visited,FALSE AS cycle,1 AS depth
                    FROM layouts l WHERE l.id=:id AND l.site_id=:site
                    UNION ALL
                    SELECT p.id,p.parent_id,p.name,p.template_path,p.site_id,
                           CONCAT(c.visited,p.id,','),LOCATE(CONCAT(',',p.id,','),c.visited)>0,c.depth+1
                    FROM layouts p JOIN layout_chain c ON p.id=c.parent_id AND p.site_id=c.site_id
                    WHERE c.cycle=FALSE AND c.depth<=:depth
                ) SELECT id,parent_id,name,template_path,cycle,depth FROM layout_chain ORDER BY depth DESC
                SQL);
            $q->execute(['id' => $id, 'site' => $this->siteId, 'depth' => min(128, $maximumDepth)]);
            return $q->fetchAll(PDO::FETCH_ASSOC);
        }
        $q = $this->db->prepare(<<<'SQL'
            WITH RECURSIVE layout_chain AS (
                SELECT l.id, l.parent_id, l.name, l.template_path, l.site_id, ARRAY[l.id]::bigint[] AS visited, FALSE AS cycle, 1 AS depth
                FROM layouts l WHERE l.id = :id AND l.site_id = :site
                UNION ALL
                SELECT p.id, p.parent_id, p.name, p.template_path, p.site_id, c.visited || p.id, p.id = ANY(c.visited), c.depth + 1
                FROM layouts p JOIN layout_chain c ON p.id = c.parent_id AND p.site_id = c.site_id
                WHERE c.cycle = FALSE AND c.depth <= :depth
            ) SELECT id, parent_id, name, template_path, cycle, depth FROM layout_chain ORDER BY depth DESC
            SQL);
        $q->execute(['id' => $id, 'site' => $this->siteId, 'depth' => $maximumDepth]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function create(string $name, string $templatePath, ?string $description, ?int $folderId = null): int
    {
        (new FolderRepository($this->db, $this->siteId, 'layouts'))->assertFolder($folderId);
        $q = $this->db->prepare('INSERT INTO layouts(site_id, code, name, template_path, description, folder_id) VALUES (:site, :code, :name, :path, :description, :folder) ' . \Core\Database\SqlDialect::returningId($this->db));
        $q->execute(['site' => $this->siteId, 'code' => $this->codeFromTemplatePath($templatePath), 'name' => $name, 'path' => $templatePath, 'description' => $description, 'folder' => $folderId]);
        return \Core\Database\SqlDialect::insertedId($this->db, $q);
    }
    public function update(int $id, string $name, ?string $description, ?int $folderId = null): void
    {
        (new FolderRepository($this->db, $this->siteId, 'layouts'))->assertFolder($folderId);
        $q = $this->db->prepare('UPDATE layouts SET name = :name, description = :description, folder_id = :folder, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND site_id = :site');
        $q->execute(['id' => $id, 'site' => $this->siteId, 'name' => $name, 'description' => $description, 'folder' => $folderId]);
        if ($q->rowCount() !== 1) throw new RuntimeException('Макет не найден.');
    }
    public function delete(int $id): void
    {
        $q = $this->db->prepare('DELETE FROM layouts WHERE id = :id AND site_id = :site AND is_system = FALSE');
        $q->execute(['id' => $id, 'site' => $this->siteId]);
        if ($q->rowCount() !== 1) throw new RuntimeException('Макет не найден или является системным.');
    }
    public function isTemplatePathUsed(string $templatePath): bool
    {
        $q = $this->db->prepare('SELECT 1 FROM layouts WHERE site_id = :site AND template_path = :path LIMIT 1');
        $q->execute(['site' => $this->siteId, 'path' => $templatePath]);
        return $q->fetchColumn() !== false;
    }
    private function codeFromTemplatePath(string $path): string
    {
        if (!preg_match('#^layouts/([a-z0-9][a-z0-9_-]{0,79})\.(?:html\.php|php)$#D', trim($path), $match)) throw new RuntimeException('Layout template path cannot be converted to a stable code.');
        return $match[1];
    }
}
