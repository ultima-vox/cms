<?php

declare(strict_types=1);
namespace Core\Content;

use PDO;
use PDOException;
use RuntimeException;

final readonly class FolderRepository
{
    public function __construct(private PDO $db, private int $siteId, private string $scope)
    {
        if ($siteId < 1 || !preg_match('/^[a-z][a-z0-9._-]{0,79}$/D', $scope)) throw new RuntimeException('Invalid folder context.');
    }
    public static function id(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0' || $value === 0) return null;
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $value)) throw new RuntimeException('Некорректная папка.');
        return (int) $value;
    }
    public function find(int $id): ?array
    {
        $q = $this->db->prepare('SELECT * FROM content_folders WHERE site_id = :site AND scope = :scope AND id = :id');
        $q->execute(['site' => $this->siteId, 'scope' => $this->scope, 'id' => $id]);
        return ($row = $q->fetch(PDO::FETCH_ASSOC)) !== false ? $row : null;
    }
    public function assertFolder(?int $id): void
    {
        if ($id !== null && $this->find($id) === null) throw new RuntimeException('Папка не найдена в текущем разделе сайта.');
    }
    public function all(): array
    {
        $q = $this->db->prepare('SELECT * FROM content_folders WHERE site_id = :site AND scope = :scope ORDER BY lower(name), id');
        $q->execute(['site' => $this->siteId, 'scope' => $this->scope]);
        $children = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $children[(int) ($row['parent_id'] ?? 0)][] = $row;
        $result = [];
        $walk = function (int $parent, array $names) use (&$walk, &$result, $children): void {
            if (count($names) > 64) throw new RuntimeException('Превышена глубина дерева папок.');
            foreach ($children[$parent] ?? [] as $row) {
                $path = [...$names, $row['name']];
                $row['label'] = implode(' / ', $path);
                $result[] = $row;
                $walk((int) $row['id'], $path);
            }
        };
        $walk(0, []);
        return $result;
    }
    public function save(?int $id, string $name, ?int $parent): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 160) throw new RuntimeException('Название папки: от 1 до 160 символов.');
        $this->assertFolder($parent);
        if ($id !== null) $this->assertFolder($id);
        try {
            $q = $this->db->prepare($id === null
                ? 'INSERT INTO content_folders(site_id, scope, parent_id, name) VALUES (:site, :scope, :parent, :name) RETURNING id'
                : 'UPDATE content_folders SET parent_id = :parent, name = :name, updated_at = CURRENT_TIMESTAMP WHERE site_id = :site AND scope = :scope AND id = :id RETURNING id');
            $data = ['site' => $this->siteId, 'scope' => $this->scope, 'parent' => $parent, 'name' => $name];
            if ($id !== null) $data['id'] = $id;
            $q->execute($data);
            $saved = $q->fetchColumn();
            if ($saved === false) throw new RuntimeException('Папка не найдена.');
            return (int) $saved;
        } catch (PDOException $e) {
            throw new RuntimeException($e->getCode() === '23505' ? 'Папка с таким названием уже существует.' : 'Не удалось переместить папку: проверьте родителя и отсутствие цикла.', 0, $e);
        }
    }
    public function delete(int $id): void
    {
        try {
            $q = $this->db->prepare('DELETE FROM content_folders WHERE site_id = :site AND scope = :scope AND id = :id');
            $q->execute(['site' => $this->siteId, 'scope' => $this->scope, 'id' => $id]);
            if ($q->rowCount() !== 1) throw new RuntimeException('Папка не найдена.');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23503') throw $e;
            throw new RuntimeException('Сначала переместите содержимое и дочерние папки. Удалить можно только пустую папку.');
        }
    }
    public function browser(mixed $selected, string $path): array
    {
        $all = $selected === 'all';
        $id = $all ? null : self::id($selected);
        $this->assertFolder($id);
        $options = $this->all();
        $children = array_values(array_filter($options, static fn ($f) => (int) ($f['parent_id'] ?? 0) === ($id ?? 0)));
        $ancestors = []; $cursor = $id === null ? null : $this->find($id);
        while ($cursor !== null && $cursor['parent_id'] !== null) {
            $cursor = $this->find((int) $cursor['parent_id']);
            if ($cursor !== null) array_unshift($ancestors, $cursor);
            if (count($ancestors) > 64) throw new RuntimeException('Некорректное дерево папок.');
        }
        return ['ancestors' => $ancestors, 'scope' => $this->scope, 'path' => $path, 'selected' => $id, 'all' => $all, 'current' => $id === null ? null : $this->find($id), 'children' => $children, 'options' => $options];
    }
    public function filter(array $rows, array $browser): array
    {
        return $browser['all'] ? $rows : array_values(array_filter($rows, static fn ($r) => (int) ($r['folder_id'] ?? 0) === ($browser['selected'] ?? 0)));
    }
}
