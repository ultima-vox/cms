<?php

declare(strict_types=1);

namespace Core\Controller;

use Core\Delivery\Page\PageCache;
use Core\Delivery\Page\PageCacheEntry;
use Core\Delivery\ResourceHint;
use Core\Delivery\ResourceHintHeader;
use Core\Extension\Api\DeliveryApi;
use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\NodeRepository;
use Core\Routing\SystemPathPolicy;
use Core\Security\AuthService;
use Core\Site\SiteContext;
use Core\Site\SiteResolver;
use Core\View\FrontendRenderer;
use Core\View\PageViewModel;
use Core\View\Render\RenderContext;
use Core\View\SafeHtml;

final class NodeController
{
    public function __construct(
        private readonly NodeRepository $nodes,
        private readonly SiteResolver $sites,
        private readonly FrontendRenderer $view,
        private readonly PageCache $pageCache,
        private readonly DeliveryApi $delivery,
        private readonly AuthService $auth,
    ) {
    }

    /** @param array<string, string> $variables */
    public function resolve(Request $request, array $variables = []): Response
    {
        unset($variables);

        if (SystemPathPolicy::isReserved($request->path)) {
            return $this->notFound($request->path);
        }

        $site = $this->sites->resolve($request);
        if ($site === null) {
            return $this->notFound($request->path);
        }

        $cacheEligible = $this->delivery->pageCacheAllowed(
            $request,
            $site,
            $this->auth->hasAuthenticatedSession(),
        );
        $variant = $this->hostVariant($site);

        if ($cacheEligible) {
            $cached = $this->pageCache->get($site->id, $request->path, $variant);
            if ($cached !== null) {
                return $this->pageResponse($cached, 'HIT');
            }
        }

        $node = $this->nodes->findPublishedByPath($site->id, $request->path);
        if ($node === null) {
            return $this->notFound($request->path);
        }

        $template = isset($node['template_path']) && is_string($node['template_path']) && $node['template_path'] !== ''
            ? $node['template_path']
            : 'layouts/main.html.php';

        $title = trim((string) ($node['title'] ?? ''));
        if ($title === '') {
            $title = (string) ($node['name'] ?? '');
        }

        $page = new PageViewModel(
            id: (int) $node['id'],
            name: (string) ($node['name'] ?? ''),
            title: $title,
            path: (string) ($node['path'] ?? $request->path),
            content: SafeHtml::fromTrustedStorage((string) ($node['content'] ?? '')),
            metaDescription: isset($node['meta_description']) && is_string($node['meta_description'])
                ? $node['meta_description']
                : null,
        );

        $renderContext = new RenderContext();
        $renderContext->dependency('site:' . $site->id);
        $renderContext->dependency('node:' . (int) $node['id']);
        $renderContext->dependency('site:' . $site->id . ':node:' . (int) $node['id']);
        $renderContext->dependency('template:' . $template);
        if (isset($node['layout_id']) && is_numeric($node['layout_id'])) {
            $renderContext->dependency('layout:' . (int) $node['layout_id']);
        }

        $result = $this->view->renderResult($template, [
            'site' => $site,
            'page' => $page,
            'node' => $node,
            'content' => (string) ($node['content'] ?? ''),
        ], $renderContext);

        if ($cacheEligible) {
            $entry = $this->pageCache->put($site->id, $request->path, $variant, $result);
            if ($entry !== null) {
                return $this->pageResponse($entry, 'MISS');
            }
        }

        return $this->bypassResponse($result->html, $result->context);
    }

    private function pageResponse(PageCacheEntry $entry, string $cacheStatus): Response
    {
        return $this->response(
            $entry->html,
            $entry->resourceHints,
            $cacheStatus,
            'public, max-age=0, must-revalidate',
        );
    }

    private function bypassResponse(string $html, RenderContext $context): Response
    {
        return $this->response(
            $html,
            $context->resourceHints(),
            'BYPASS',
            'private, no-store',
        );
    }

    /** @param list<ResourceHint> $resourceHints */
    private function response(
        string $html,
        array $resourceHints,
        string $cacheStatus,
        string $cacheControl,
    ): Response {
        $response = Response::html($html)
            ->withHeader('X-Ultima-Cache', $cacheStatus)
            ->withHeader('Cache-Control', $cacheControl);

        $link = ResourceHintHeader::render($resourceHints);
        if ($link !== null) {
            $response = $response->withHeader('Link', $link);
        }

        return $response;
    }

    private function hostVariant(SiteContext $site): string
    {
        return 'host-' . substr(hash('sha256', $site->host), 0, 20);
    }

    private function notFound(string $path): Response
    {
        return Response::html(
            $this->view->render('errors/404.html.php', ['path' => $path]),
            404,
        )->withHeader('Cache-Control', 'no-store');
    }
}
