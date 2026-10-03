<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\Page\PageExecutionContext;
use Core\Page\PageExecutorInterface;
use Core\View\SafeHtml;
use RuntimeException;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

final readonly class DocumentsPageExecutor implements PageExecutorInterface
{
    public function __construct(
        private DocumentRepository $repository,
        private DocumentRenderer $renderer,
    ) {
    }

    public function execute(PageExecutionContext $context): SafeHtml
    {
        $code = $context->configuration['document'] ?? null;
        if (!is_string($code) || trim($code) === '') {
            throw new RuntimeException('Documents page executor requires a document code.');
        }

        $document = $this->repository->findActiveByCode($context->site->id, $code);
        if ($document === null) {
            throw new RuntimeException(sprintf(
                'Active document "%s" was not found for the current site.',
                trim($code),
            ));
        }

        return $this->renderer->render($document, $context->renderContext);
    }
}
