<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\Page\PageExecutionStageInterface;
use Core\Page\PageRuntime;
use Core\View\SafeHtml;

final readonly class DocumentsIntroStage implements PageExecutionStageInterface
{
    /** @param array<string, mixed> $document */
    public function __construct(
        private array $document,
        private DocumentRenderer $renderer,
    ) {
    }

    public function execute(PageRuntime $page): SafeHtml
    {
        $intro = $this->renderer->render(
            $this->document,
            $page->context()->renderContext,
        );

        $next = $page->execute();

        return SafeHtml::fromTrustedStorage($intro->value() . $next->value());
    }
}
