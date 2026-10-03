<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\View\Render\TemplateFacadeContext;
use Core\View\SafeHtml;
use RuntimeException;

final readonly class DocumentFacade
{
    /** @param array<string, mixed> $document */
    public function __construct(
        private TemplateFacadeContext $context,
        private DocumentRenderer $renderer,
        private array $document,
    ) {
    }

    public function id(): int
    {
        $id = $this->document['id'] ?? null;
        if (!is_numeric($id) || (int) $id < 1) {
            throw new RuntimeException('Resolved document id is invalid.');
        }

        return (int) $id;
    }

    public function code(): string
    {
        return (string) ($this->document['code'] ?? '');
    }

    public function name(): string
    {
        return (string) ($this->document['name'] ?? '');
    }

    public function execute(): SafeHtml
    {
        return $this->renderer->render(
            $this->document,
            $this->context->renderEngine()->context(),
        );
    }
}
