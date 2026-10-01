<?php

declare(strict_types=1);

namespace Core\Page;

use Core\View\SafeHtml;

interface PageExecutorInterface
{
    public function execute(PageExecutionContext $context): SafeHtml;
}
