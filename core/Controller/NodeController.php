<?php

declare(strict_types=1);

namespace Core\Controller;

use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\InfosystemRepository;
use Core\Repository\NodeRepository;
use Core\View\TwigRenderer;

final class NodeController
{
    public function __construct(
        private readonly NodeRepository $nodes,
        private readonly InfosystemRepository $infosystems,
        private readonly TwigRenderer $view,
    ) {
    }

    /** @param array<string, string> $variables */
    public function resolve(Request $request, array $variables = []): Response
    {
        unset($variables);

        $node = $this->nodes->findPublishedByPath($request->path);

        if ($node === null) {
            return Response::html(
                $this->view->render('errors/404.twig', ['path' => $request->path]),
                404,
            );
        }

        $items = [];
        $infosystemId = $node['infosystem_id'] ?? null;

        if (is_int($infosystemId) || (is_string($infosystemId) && ctype_digit($infosystemId))) {
            $items = $this->infosystems->findPublishedItems((int) $infosystemId);
        }

        $template = isset($node['template_path']) && is_string($node['template_path']) && $node['template_path'] !== ''
            ? $node['template_path']
            : 'layouts/main.twig';

        return Response::html($this->view->render($template, [
            'node' => $node,
            'content' => (string) ($node['content'] ?? ''),
            'items' => $items,
        ]));
    }
}
