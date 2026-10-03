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
        foreach (array_keys($context->requestedConfiguration) as $key) {
            if ($key !== 'document') {
                throw new RuntimeException(sprintf(
                    'Unknown Documents provisioning configuration key: %s.',
                    (string) $key,
                ));
            }
        }

        if (array_key_exists('document', $context->requestedConfiguration)) {
            $requested = $context->requestedConfiguration['document'];
            if (!is_string($requested) || trim($requested) === '') {
                throw new RuntimeException('Documents provisioning requires a non-empty document code when specified.');
            }

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
