<?php

declare(strict_types=1);

namespace Core\Page;

use Core\View\SafeHtml;

final readonly class CoreContentPageExecutor implements PageExecutorInterface
{
    public function execute(PageExecutionContext $context): SafeHtml
    {
        $nodeId = isset($context->node['id']) && is_numeric($context->node['id'])
            ? (int) $context->node['id']
            : 0;

        if ($nodeId > 0) {
            $context->renderContext->dependency('node:' . $nodeId . ':content');
        }

        return SafeHtml::fromTrustedStorage((string) ($context->node['content'] ?? ''));
    }
}
