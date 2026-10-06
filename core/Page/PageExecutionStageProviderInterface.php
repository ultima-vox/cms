<?php

declare(strict_types=1);

namespace Core\Page;

interface PageExecutionStageProviderInterface
{
    /** @return list<PageExecutionStageInterface> */
    public function stages(PageExecutionContext $context): array;
}
