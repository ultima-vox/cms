<?php

declare(strict_types=1);

namespace Core\Page;

use Core\View\SafeHtml;

final readonly class PageExecutorStage implements PageExecutionStageInterface
{
    public function __construct(
        private PageExecutorInterface $executor,
        private PageExecutionContext $context,
    ) {
    }

    public function execute(PageRuntime $page): SafeHtml
    {
        unset($page);

        return $this->executor->execute($this->context);
    }
}
