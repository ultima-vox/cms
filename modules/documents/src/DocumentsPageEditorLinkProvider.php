<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\Page\PageEditorLinkProviderInterface;
use Core\Security\AuthService;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

final readonly class DocumentsPageEditorLinkProvider implements PageEditorLinkProviderInterface
{
    public function __construct(private DocumentRepository $documents, private int $siteId)
    {
    }

    public function editorUrl(array $node, AuthService $auth): ?string
    {
        if (!$auth->can('documents.manage') || (int) ($node['site_id'] ?? 0) !== $this->siteId) {
            return null;
        }
        $config = $node['page_config'] ?? [];
        if (is_string($config)) {
            $config = json_decode($config, true, flags: JSON_THROW_ON_ERROR);
        }
        if (!is_array($config)) {
            return null;
        }
        $config = (new DocumentsPageConfigurationValidator())->validate($config);
        $document = $this->documents->findActiveByCode($this->siteId, $config['document']);
        return $document !== null ? '/admin/documents/' . (int) $document['id'] . '/edit' : null;
    }
}
