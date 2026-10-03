<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\Site\SiteContext;
use Core\View\Render\TemplateFacadeContext;
use RuntimeException;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

final class DocumentsFacade
{
    /** @var array<string, DocumentFacade> */
    private array $resolved = [];

    public function __construct(
        private readonly TemplateFacadeContext $context,
        private readonly DocumentRepository $repository,
        private readonly DocumentRenderer $renderer,
    ) {
    }

    public function get(string $code): DocumentFacade
    {
        $code = strtolower(trim($code));
        if (isset($this->resolved[$code])) {
            return $this->resolved[$code];
        }

        $site = $this->context->variables()['site'] ?? null;
        if (!$site instanceof SiteContext) {
            throw new RuntimeException('Documents facade requires the current site context.');
        }

        $document = $this->repository->findActiveByCode($site->id, $code);
        if ($document === null) {
            throw new RuntimeException(sprintf('Document "%s" was not found for the current site.', $code));
        }

        return $this->resolved[$code] = new DocumentFacade(
            $this->context,
            $this->renderer,
            $document,
        );
    }
}
