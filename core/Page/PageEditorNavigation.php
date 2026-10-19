<?php

declare(strict_types=1);

namespace Core\Page;

use Core\Extension\Api\PagesApi;
use Core\Repository\StructureRepository;
use Core\Security\AuthService;

final readonly class PageEditorNavigation
{
    public function __construct(
        private PagesApi $pages,
        private StructureRepository $structure,
        private AuthService $auth,
        private int $siteId,
    ) {
    }

    /** @return array{url:string,parameters:array{_page:string,_page_site:string}}|null */
    public function returnContext(array $input, string $editorUrl): ?array
    {
        $id = $input['_page'] ?? null;
        if (($input['_page_site'] ?? null) !== (string) $this->siteId
            || !is_string($id) || !preg_match('/^[1-9][0-9]{0,17}$/D', $id)) {
            return null;
        }
        $node = $this->structure->find((int) $id);
        if ($node === null || (int) $node['site_id'] !== $this->siteId
            || $this->pages->editorUrl($node, $this->auth) !== $editorUrl) {
            return null;
        }
        return [
            'url' => '/admin/structure/' . (int) $id . '/edit',
            'parameters' => ['_page' => $id, '_page_site' => (string) $this->siteId],
        ];
    }
}
