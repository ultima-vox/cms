<?php

declare(strict_types=1);

namespace Core\Page;

use Core\Http\Request;
use Core\Site\SiteContext;
use Core\View\Render\RenderContext;

final readonly class PageExecutionContext
{
    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        public Request $request,
        public SiteContext $site,
        public array $node,
        public array $configuration,
        public RenderContext $renderContext,
    ) {
    }
}
