<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Extension\Api\TemplatesApi;
use Core\Page\PageExecutionContext;
use Core\Page\PageExecutorInterface;
use Core\Page\PageNotFoundException;
use Core\Repository\NodeModuleBindingRepository;
use Core\View\Render\RenderEngine;
use Core\View\Render\TemplateFacadeContext;
use Core\View\Render\ViewTemplateRenderer;
use Core\View\SafeHtml;
use RuntimeException;
use UltimaVox\Modules\Infosystem\Repository\InfosystemRepository;

final readonly class InfosystemItemPageExecutor implements PageExecutorInterface
{
    public function __construct(
        private InfosystemRepository $repository,
        private NodeModuleBindingRepository $bindings,
        private TemplatesApi $templates,
        private string $rootPath,
    ) {
    }

    public function execute(PageExecutionContext $context): SafeHtml
    {
        $nodeId = (int) ($context->node['id'] ?? 0);
        if ($nodeId < 1) {
            throw new RuntimeException('Infosystem item page requires a positive node id.');
        }

        $code = $this->bindings->findTargetKey($nodeId, 'infosystem', 'primary');
        if ($code === null) {
            throw new PageNotFoundException('Page has no infosystem binding.');
        }

        $infosystem = $this->repository->findActiveByCode($context->site->id, $code);
        if ($infosystem === null) {
            throw new PageNotFoundException('Bound infosystem is inactive or missing.');
        }

        $item = $this->repository->findPublishedItemByPath(
            $context->site->id,
            (int) $infosystem['id'],
            (string) $context->configuration['item_path'],
        );
        if ($item === null) {
            throw new PageNotFoundException('Infosystem item is not publicly visible.');
        }

        $renderContext = $context->renderContext;
        $siteId = $context->site->id;
        $infosystemId = (int) $infosystem['id'];
        $itemId = (int) $item['id'];
        $renderContext->dependency('site:' . $siteId . ':infosystem:' . $infosystemId);
        $renderContext->dependency('infosystem:' . $infosystemId);
        $renderContext->dependency('site:' . $siteId . ':infosystem_item:' . $itemId);
        $renderContext->dependency('infosystem_item:' . $itemId);

        $templateContext = new TemplateFacadeContext(
            new RenderEngine($renderContext),
            ['site' => $context->site, 'node' => $context->node],
            new ViewTemplateRenderer($this->rootPath, $this->templates),
        );

        $view = (string) ($context->configuration['view'] ?? 'infosystem.item');
        return SafeHtml::fromTrustedStorage($templateContext->renderView(
            $view,
            'infosystem.item',
            ['infosystem' => $infosystem, 'item' => $item],
        ));
    }
}
