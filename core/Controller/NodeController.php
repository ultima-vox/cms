<?php

declare(strict_types=1);

namespace Core\Controller;

use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\InfosystemRepository;
use Core\Repository\NodeRepository;
use Core\View\FrontendRenderer;
use Core\View\PageViewModel;
use Core\View\SafeHtml;

final class NodeController
{
    public function __construct(
        private readonly NodeRepository $nodes,
        private readonly InfosystemRepository $infosystems,
        private readonly FrontendRenderer $view,
    ) {
    }

    /** @param array<string, string> $variables */
    public function resolve(Request $request, array $variables = []): Response
    {
        unset($variables);

        $node = $this->nodes->findPublishedByPath($request->path);

        if ($node === null) {
            return Response::html(
                $this->view->render('errors/404.html.php', ['path' => $request->path]),
                404,
            );
        }

        $template = isset($node['template_path']) && is_string($node['template_path']) && $node['template_path'] !== ''
            ? $node['template_path']
            : 'layouts/main.html.php';

        // Legacy Twig layouts expect an eager `items` array. PHP layouts resolve content lazily through module facades.
        $items = [];
        $infosystemId = $node['infosystem_id'] ?? null;

        if (str_ends_with($template, '.twig')
            && (is_int($infosystemId) || (is_string($infosystemId) && ctype_digit($infosystemId)))) {
            $items = $this->infosystems->findPublishedItems((int) $infosystemId);
        }

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

        return Response::html($this->view->render($template, [
            'page' => $page,
            'node' => $node,
            'content' => (string) ($node['content'] ?? ''),
            'items' => $items,
        ]));
    }
}
