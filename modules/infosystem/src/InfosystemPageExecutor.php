<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Extension\Api\EventsApi;
use Core\Extension\Api\TemplatesApi;
use Core\Page\PageExecutionContext;
use Core\Page\PageExecutorInterface;
use Core\Repository\NodeModuleBindingRepository;
use Core\View\Render\RenderEngine;
use Core\View\Render\TemplateFacadeContext;
use Core\View\Render\ViewTemplateRenderer;
use Core\View\SafeHtml;
use RuntimeException;
use UltimaVox\Modules\Infosystem\Repository\InfosystemRepository;

final readonly class InfosystemPageExecutor implements PageExecutorInterface
{
    public function __construct(
        private InfosystemRepository $repository,
        private NodeModuleBindingRepository $bindings,
        private EventsApi $events,
        private TemplatesApi $templates,
        private string $rootPath,
    ) {
    }

    public function execute(PageExecutionContext $context): SafeHtml
    {
        $nodeId = isset($context->node['id']) && is_numeric($context->node['id'])
            ? (int) $context->node['id']
            : 0;
        if ($nodeId < 1) {
            throw new RuntimeException('Infosystem page executor requires a positive node id.');
        }

        $code = $this->bindings->findTargetKey($nodeId, 'infosystem', 'primary');
        if ($code === null) {
            throw new RuntimeException('Infosystem page executor requires a primary infosystem binding.');
        }

        $infosystem = $this->repository->findActiveByCode($context->site->id, $code);
        if ($infosystem === null) {
            throw new RuntimeException(sprintf(
                'Active infosystem "%s" was not found for the current site.',
                $code,
            ));
        }

        $templateContext = new TemplateFacadeContext(
            new RenderEngine($context->renderContext),
            [
                'site' => $context->site,
                'node' => $context->node,
            ],
            new ViewTemplateRenderer($this->rootPath, $this->templates),
        );

        $source = new InfosystemItemsSource(
            $templateContext,
            $this->repository,
            $this->events,
            $infosystem,
        );

        $view = $context->configuration['view'] ?? null;
        if (is_string($view) && trim($view) !== '') {
            $source = $source->template($view);
        }

        $limit = $context->configuration['limit'] ?? null;
        if (is_int($limit) || (is_string($limit) && ctype_digit($limit))) {
            $source = $source->limit((int) $limit);
        }

        $html = $source->show();
        if (($context->configuration['include_content'] ?? true) !== false) {
            $context->renderContext->dependency('node:' . $nodeId . ':content');
            $html = (string) ($context->node['content'] ?? '') . $html;
        }

        return SafeHtml::fromTrustedStorage($html);
    }
}
