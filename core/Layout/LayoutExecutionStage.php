<?php

declare(strict_types=1);

namespace Core\Layout;

use Core\Page\PageExecutionStageInterface;
use Core\Page\PageRuntime;
use Core\View\PhpRenderer;
use Core\View\SafeHtml;

final readonly class LayoutExecutionStage implements PageExecutionStageInterface
{
    public function __construct(
        private LayoutDefinition $layout,
        private PhpRenderer $renderer,
    ) {
    }

    public function execute(PageRuntime $page): SafeHtml
    {
        $context = $page->context();
        $context->renderContext->dependency('layout:' . $this->layout->id);
        $context->renderContext->dependency('template:' . $this->layout->templatePath);

        $result = $this->renderer->renderResult(
            $this->layout->templatePath,
            [
                'page' => $page,
                'site' => $context->site,
                'node' => $context->node,
                'layout' => $this->layout,
            ],
            $context->renderContext,
        );

        return SafeHtml::fromTrustedStorage($result->html);
    }
}
