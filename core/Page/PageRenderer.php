<?php

declare(strict_types=1);

namespace Core\Page;

use Core\Extension\Api\PagesApi;
use Core\Http\Request;
use Core\Layout\LayoutExecutionStage;
use Core\Layout\LayoutHierarchyResolver;
use Core\Repository\NodeRepository;
use Core\Routing\SystemPathPolicy;
use Core\Site\SiteContext;
use Core\View\PhpRenderer;
use Core\View\Render\RenderContext;
use Core\View\Render\RenderResult;

final readonly class PageRenderer
{
    public function __construct(
        private NodeRepository $nodes,
        private PhpRenderer $view,
        private PagesApi $pages,
        private LayoutHierarchyResolver $layouts,
    ) {
    }

    public function render(Request $request, SiteContext $site): ?RenderResult
    {
        if (SystemPathPolicy::isReserved($request->path)) {
            return null;
        }

        $node = $this->nodes->findPublishedByPath($site->id, $request->path);
        if ($node === null) {
            return null;
        }

        $selection = PageTypeSelection::fromNode($node);
        $definition = $this->pages->definition($selection->code);
        $configuration = $definition->validateConfiguration($selection->configuration);

        $renderContext = new RenderContext();
        $renderContext->dependency('site:' . $site->id);
        $renderContext->dependency('node:' . (int) $node['id']);
        $renderContext->dependency('site:' . $site->id . ':node:' . (int) $node['id']);
        $renderContext->dependency('page_type:' . $definition->code);

        $context = new PageExecutionContext(
            $request,
            $site,
            $node,
            $configuration,
            $renderContext,
        );

        $layoutId = isset($node['layout_id']) && is_numeric($node['layout_id'])
            ? (int) $node['layout_id']
            : 0;
        $layouts = $this->layouts->forSite($site->id);
        $layoutDefinitions = $layoutId > 0
            ? $layouts->resolve($layoutId)
            : $layouts->resolveDefault();

        $stages = [];
        foreach ($layoutDefinitions as $layout) {
            $stages[] = new LayoutExecutionStage($layout, $this->view);
        }
        foreach ($this->pages->stages($context) as $stage) {
            $stages[] = $stage;
        }
        $stages[] = new PageExecutorStage($definition->executor);

        $page = new PageRuntime(
            $context,
            new PageExecutionChain($stages),
        );
        try {
            return new RenderResult($page->start()->value(), $renderContext);
        } catch (PageNotFoundException) {
            return null;
        }
    }
}
