<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\Page\PageTypeProvisionerInterface;
use Core\Page\PageTypeProvisioningContext;
use RuntimeException;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

final readonly class DocumentsPageProvisioner implements PageTypeProvisionerInterface
{
    public function __construct(private DocumentRepository $repository)
    {
    }

    public function provision(PageTypeProvisioningContext $context): array
    {
        $requested = $context->requestedConfiguration['document'] ?? null;
        if (is_string($requested) && trim($requested) !== '') {
            $code = strtolower(trim($requested));
            if ($this->repository->findActiveByCode($context->siteId, $code) === null) {
                throw new RuntimeException(sprintf(
                    'Document "%s" was not found for the current site.',
                    $code,
                ));
            }

            return ['document' => $code];
        }

        $code = 'node-' . $context->nodeId;
        $existing = $this->repository->findActiveByCode($context->siteId, $code);
        if ($existing !== null) {
            return ['document' => $code];
        }

        $name = trim($context->nodeTitle) !== ''
            ? trim($context->nodeTitle)
            : trim($context->nodeName);

        $documentId = $this->repository->create($context->siteId, $code, $name);
        $versionId = $this->repository->createDraftVersion($documentId, '');
        $this->repository->publishVersion($documentId, $versionId);

        return ['document' => $code];
    }
}
