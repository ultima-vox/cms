<?php

declare(strict_types=1);

namespace Core\Page;

use Core\View\SafeHtml;

final readonly class PageExecutorStage implements PageExecutionStageInterface
{
    public function __construct(private PageExecutorInterface $executor)
    {
    }

    public function execute(PageRuntime $page): SafeHtml
    {
        return $this->executor->execute($page->context());
    }
}
