<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem\Admin;

use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\NodeModuleBindingRepository;
use Core\Repository\StructureRepository;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\View\TwigRenderer;
use RuntimeException;
use UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository;

final readonly class InfosystemBindingController
{
    public function __construct(
        private AuthService $auth,
        private InfosystemManagementRepository $infosystems,
        private StructureRepository $structure,
        private NodeModuleBindingRepository $bindings,
        private int $siteId,
        private TwigRenderer $view,
    ) {
        if ($this->siteId < 1) {
            throw new RuntimeException('Site id must be positive.');
        }
    }

    /** @param array<string, string> $variables */
    public function edit(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        $infosystem = $this->infosystem($variables);
        if ($infosystem instanceof Response) {
            return $infosystem;
        }

        return $this->form($infosystem, isset($request->query['saved']));
    }

    /** @param array<string, string> $variables */
    public function update(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        $csrf = $request->post['_csrf'] ?? null;
        if (!Csrf::validate(is_string($csrf) ? $csrf : null)) {
            return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
        }

        $infosystem = $this->infosystem($variables);
        if ($infosystem instanceof Response) {
            return $infosystem;
        }

        try {
            $nodeIds = $this->nodeIds($request->post['node_ids'] ?? []);
            $this->bindings->replaceTargetNodes(
                $this->siteId,
                'infosystem',
                'primary',
                (string) $infosystem['code'],
                $nodeIds,
            );
        } catch (RuntimeException $exception) {
            return $this->form($infosystem, false, $exception->getMessage(), 422);
        }

        return Response::redirect(
            '/admin/infosystems/' . (int) $infosystem['id'] . '/bindings?saved=1'
        );
    }

    /** @param array<string, mixed> $infosystem */
    private function form(
        array $infosystem,
        bool $saved = false,
        ?string $error = null,
        int $status = 200,
    ): Response {
        $nodes = $this->flattenTree($this->structure->all());
        $bound = $this->bindings->nodeIdsForTarget(
            $this->siteId,
            'infosystem',
            'primary',
            (string) $infosystem['code'],
        );

        return Response::html($this->view->render('admin/infosystems/bindings.twig', [
            'infosystem' => $infosystem,
            'nodes' => $nodes,
            'bound_node_ids' => array_fill_keys($bound, true),
            'csrf_token' => Csrf::token(),
            'saved' => $saved,
            'error' => $error,
        ]), $status);
    }

    /** @param array<string, string> $variables @return array<string, mixed>|Response */
    private function infosystem(array $variables): array|Response
    {
        $raw = $variables['id'] ?? '';
        if (!ctype_digit($raw) || (int) $raw < 1) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        $infosystem = $this->infosystems->find((int) $raw);
        return $infosystem ?? Response::html('<h1>404 Not Found</h1>', 404);
    }

    /** @return list<int> */
    private function nodeIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (!is_array($raw)) {
            throw new RuntimeException('Некорректный список разделов сайта.');
        }

        $ids = [];
        foreach ($raw as $value) {
            if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) {
                throw new RuntimeException('Некорректный идентификатор раздела сайта.');
            }
            $ids[(int) $value] = (int) $value;
        }
        ksort($ids, SORT_NUMERIC);

        return array_values($ids);
    }

    private function requirePermission(): ?Response
    {
        if ($this->auth->user() === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->can('infosystems.manage')) {
            return Response::html('<h1>403 Forbidden</h1>', 403);
        }
        return null;
    }

    /** @param list<array<string, mixed>> $nodes @return list<array<string, mixed>> */
    private function flattenTree(array $nodes): array
    {
        $children = [];
        foreach ($nodes as $node) {
            $parentId = $node['parent_id'] === null ? 0 : (int) $node['parent_id'];
            $children[$parentId][] = $node;
        }

        $result = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$result, $children): void {
            foreach ($children[$parentId] ?? [] as $node) {
                $node['depth'] = $depth;
                $result[] = $node;
                $walk((int) $node['id'], $depth + 1);
            }
        };
        $walk(0, 0);

        return $result;
    }
}
