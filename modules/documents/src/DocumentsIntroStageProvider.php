<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\Page\PageExecutionContext;
use Core\Page\PageExecutionStageProviderInterface;
use Core\Repository\NodeModuleBindingRepository;
use RuntimeException;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

final readonly class DocumentsIntroStageProvider implements PageExecutionStageProviderInterface
{
    public function __construct(
        private NodeModuleBindingRepository $bindings,
        private DocumentRepository $repository,
        private DocumentRenderer $renderer,
    ) {
    }

    public function stages(PageExecutionContext $context): array
    {
        $nodeId = isset($context->node['id']) && is_numeric($context->node['id'])
            ? (int) $context->node['id']
            : 0;
        if ($nodeId < 1) {
            throw new RuntimeException('Documents intro stage requires a positive node id.');
        }

        $code = $this->bindings->findTargetKey($nodeId, 'documents', 'intro');
        if ($code === null) {
            return [];
        }

        $document = $this->repository->findActiveByCode($context->site->id, $code);
        if ($document === null) {
            throw new RuntimeException(sprintf(
                'Active intro document "%s" was not found for the current site.',
                $code,
            ));
        }

        return [new DocumentsIntroStage($document, $this->renderer)];
    }
}
