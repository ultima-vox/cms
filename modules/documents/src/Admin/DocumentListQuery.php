<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents\Admin;

/** Validated, bounded input for the document list; never contains SQL identifiers. */
final readonly class DocumentListQuery
{
    private function __construct(
        public string $search,
        public string $status,
        public string $sort,
        public int $page,
        public int $perPage,
        public ?int $folderId,
        public bool $allFolders,
    ) {
    }

    public static function fromQuery(array $query, ?int $folderId, bool $allFolders): self
    {
        $string = static fn (mixed $value): string => is_string($value) ? trim($value) : '';
        $status = $string($query['status'] ?? '');
        $sort = $string($query['sort'] ?? '');
        $page = $string($query['page'] ?? '');
        $perPage = $string($query['per_page'] ?? '');

        return new self(
            mb_substr($string($query['q'] ?? ''), 0, 160),
            in_array($status, ['published', 'draft', 'unpublished'], true) ? $status : '',
            in_array($sort, ['name', 'name_desc', 'code', 'updated'], true) ? $sort : 'name',
            preg_match('/^[1-9][0-9]{0,5}$/D', $page) ? (int) $page : 1,
            in_array($perPage, ['25', '50', '100'], true) ? (int) $perPage : 25,
            $folderId,
            $allFolders,
        );
    }

    public function url(int $page): string
    {
        return '/admin/documents?' . http_build_query($this->parameters($page), '', '&', PHP_QUERY_RFC3986);
    }

    public function parameters(int $page): array
    {
        return [
            'folder' => $this->allFolders ? 'all' : ($this->folderId ?? ''),
            'q' => $this->search,
            'status' => $this->status,
            'sort' => $this->sort,
            'per_page' => $this->perPage,
            'page' => max(1, $page),
        ];
    }

    public function returnParameters(int $siteId, int $page): array
    {
        return ['_list_site' => $siteId, '_list' => $this->parameters($page)];
    }

    public function editUrl(int $id, int $siteId, int $page): string
    {
        return '/admin/documents/' . $id . '/edit?' . http_build_query(
            $this->returnParameters($siteId, $page), '', '&', PHP_QUERY_RFC3986,
        );
    }

    public function createUrl(int $siteId, int $page): string
    {
        return '/admin/documents/create?' . http_build_query(
            ['folder' => $this->folderId ?? ''] + $this->returnParameters($siteId, $page),
            '', '&', PHP_QUERY_RFC3986,
        );
    }
}
