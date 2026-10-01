<?php

declare(strict_types=1);

namespace Core\Page;

use Core\View\SafeHtml;

interface PageExecutionStageInterface
{
    public function execute(PageRuntime $page): SafeHtml;
}
