<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\View\Render\RenderContext;
use Core\View\SafeHtml;
use RuntimeException;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

final readonly class DocumentRenderer
{
    public function __construct(private DocumentRepository $repository)
    {
    }

    /** @param array<string, mixed> $document */
    public function render(array $document, RenderContext $context): SafeHtml
    {
        $documentId = isset($document['id']) && is_numeric($document['id'])
            ? (int) $document['id']
            : 0;
        $siteId = isset($document['site_id']) && is_numeric($document['site_id'])
            ? (int) $document['site_id']
            : 0;
        if ($documentId < 1 || $siteId < 1) {
            throw new RuntimeException('Document renderer requires a valid site-scoped document.');
        }

        $context->dependency('document:' . $documentId);
        $context->dependency('site:' . $siteId . ':document:' . $documentId);

        $version = $this->repository->currentPublishedVersion($documentId);
        if ($version === null) {
            throw new RuntimeException(sprintf(
                'Document "%s" has no published version.',
                (string) ($document['code'] ?? $documentId),
            ));
        }

        $versionId = (int) ($version['id'] ?? 0);
        if ($versionId < 1) {
            throw new RuntimeException('Published document version is invalid.');
        }
        $context->dependency('document_version:' . $versionId);

        return SafeHtml::fromTrustedStorage((string) ($version['content'] ?? ''));
    }
}
