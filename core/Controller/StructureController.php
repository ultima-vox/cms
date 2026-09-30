<?php

declare(strict_types=1);

namespace Core\Controller;

use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\AuditLogRepository;
use Core\Repository\StructureRepository;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\View\TwigRenderer;
use RuntimeException;
use Throwable;

final class StructureController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly StructureRepository $structure,
        private readonly AuditLogRepository $audit,
        private readonly TwigRenderer $view,
    ) {
    }

    /** @param array<string, string> $variables */
    public function index(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        return Response::html($this->view->render('admin/structure/index.twig', [
            'nodes' => $this->flattenTree($this->structure->all()),
            'csrf_token' => Csrf::token(),
            'saved' => isset($request->query['saved']),
            'deleted' => isset($request->query['deleted']),
        ]));
    }

    /** @param array<string, string> $variables */
    public function createForm(Request $request, array $variables = []): Response
    {
        unset($request, $variables);
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        return $this->formResponse(null);
    }

    /** @param array<string, string> $variables */
    public function editForm(Request $request, array $variables = []): Response
    {
        unset($request);
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        $node = $this->structure->find($this->routeId($variables));
        return $node === null ? Response::html('<h1>404 Not Found</h1>', 404) : $this->formResponse($node);
    }

    /** @param array<string, string> $variables */
    public function store(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requirePermission()) !== null) return $denied;
        if (!Csrf::validate($this->stringOrNull($request->post['_csrf'] ?? null))) {
            return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
        }

        try {
            $id = $this->structure->create($this->validateNodeData($request->post, null));
            $created = $this->structure->find($id);
            $this->audit($request, 'node.create', $id, ['path' => $created['path'] ?? null]);
            return Response::redirect('/admin/structure/' . $id . '/edit?saved=1');
        } catch (Throwable $exception) {
            return $this->formResponse($request->post, $exception->getMessage(), 422);
        }
    }

    /** @param array<string, string> $variables */
    public function update(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) return $denied;
        if (!Csrf::validate($this->stringOrNull($request->post['_csrf'] ?? null))) {
            return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
        }

        $id = $this->routeId($variables);
        $existing = $this->structure->find($id);
        if ($existing === null) return Response::html('<h1>404 Not Found</h1>', 404);

        try {
            $this->structure->update($id, $this->validateNodeData($request->post, $existing));
            $updated = $this->structure->find($id);
            $this->audit($request, 'node.update', $id, [
                'old_path' => $existing['path'] ?? null,
                'new_path' => $updated['path'] ?? null,
            ]);
            return Response::redirect('/admin/structure/' . $id . '/edit?saved=1');
        } catch (Throwable $exception) {
            return $this->formResponse(array_merge($existing, $request->post), $exception->getMessage(), 422);
        }
    }

    /** @param array<string, string> $variables */
    public function delete(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) return $denied;
        if (!Csrf::validate($this->stringOrNull($request->post['_csrf'] ?? null))) {
            return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
        }

        $id = $this->routeId($variables);
        $node = $this->structure->find($id);
        if ($node === null) return Response::html('<h1>404 Not Found</h1>', 404);
        if ($node['parent_id'] === null) return Response::html('<h1>Корневой узел удалить нельзя.</h1>', 409);

        $this->structure->delete($id);
        $this->audit($request, 'node.delete', $id, ['path' => $node['path']]);
        return Response::redirect('/admin/structure?deleted=1');
    }

    /** @param array<string, string> $variables */
    public function reorder(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requirePermission()) !== null) return $denied;
        if (!Csrf::validate($this->stringOrNull($request->post['_csrf'] ?? null))) {
            return Response::json(['ok' => false, 'error' => 'csrf'], 419);
        }

        $raw = $this->stringOrNull($request->post['items'] ?? null);
        if ($raw === null) return Response::json(['ok' => false, 'error' => 'items_required'], 422);

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) throw new RuntimeException('Некорректный список узлов.');

            $items = [];
            foreach ($decoded as $item) {
                if (!is_array($item) || !isset($item['id'], $item['sorting'])) {
                    throw new RuntimeException('Некорректный элемент сортировки.');
                }
                $items[] = [
                    'id' => (int) $item['id'],
                    'parent_id' => isset($item['parent_id']) && $item['parent_id'] !== null ? (int) $item['parent_id'] : null,
                    'sorting' => (int) $item['sorting'],
                ];
            }

            $this->structure->reorder($items);
            $this->audit($request, 'node.reorder', null, ['count' => count($items)]);
            return Response::json(['ok' => true]);
        } catch (Throwable $exception) {
            return Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
        }
    }

    /** @param array<string, mixed>|null $node */
    private function formResponse(?array $node, ?string $error = null, int $status = 200): Response
    {
        $form = array_merge([
            'id' => null,
            'parent_id' => null,
            'layout_id' => null,
            'name' => '',
            'slug' => '',
            'path' => null,
            'title' => '',
            'content' => '',
            'meta_description' => '',
            'status' => 'draft',
            'is_active' => true,
            'sorting' => 0,
            'publish_at' => null,
        ], $node ?? []);

        return Response::html($this->view->render('admin/structure/form.twig', [
            'node' => $form,
            'nodes' => $this->flattenTree($this->structure->all()),
            'layouts' => $this->structure->layouts(),
            'csrf_token' => Csrf::token(),
            'error' => $error,
        ]), $status);
    }

    private function requirePermission(): ?Response
    {
        if ($this->auth->user() === null) return Response::redirect('/admin/login');
        if (!$this->auth->can('structure.manage')) return Response::html('<h1>403 Forbidden</h1>', 403);
        return null;
    }

    /** @param array<string, mixed> $input @param array<string, mixed>|null $existing @return array<string, mixed> */
    private function validateNodeData(array $input, ?array $existing): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $parentId = $this->nullablePositiveInt($input['parent_id'] ?? null);
        $isRoot = $existing !== null && $existing['parent_id'] === null;

        if ($name === '') throw new RuntimeException('Название обязательно.');
        if ($isRoot || ($existing === null && $parentId === null)) {
            $slug = '';
        } elseif (!preg_match('/^[a-z0-9][a-z0-9-]{0,254}$/', $slug)) {
            throw new RuntimeException('Slug: только a-z, 0-9 и дефис, без пробелов.');
        }

        $status = (string) ($input['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            throw new RuntimeException('Некорректный статус публикации.');
        }

        $publishAt = trim((string) ($input['publish_at'] ?? ''));

        return [
            'parent_id' => $isRoot ? null : $parentId,
            'layout_id' => $this->nullablePositiveInt($input['layout_id'] ?? null),
            'name' => $name,
            'slug' => $slug,
            'title' => trim((string) ($input['title'] ?? '')) ?: $name,
            'content' => (string) ($input['content'] ?? ''),
            'meta_description' => trim((string) ($input['meta_description'] ?? '')) ?: null,
            'status' => $status,
            'is_active' => (string) ($input['is_active'] ?? '0') === '1',
            'sorting' => max(0, (int) ($input['sorting'] ?? 0)),
            'publish_at' => $publishAt !== '' ? $publishAt : null,
        ];
    }

    /** @param list<array<string, mixed>> $nodes @return list<array<string, mixed>> */
    private function flattenTree(array $nodes): array
    {
        $children = [];
        foreach ($nodes as $node) {
            $key = $node['parent_id'] === null ? 0 : (int) $node['parent_id'];
            $children[$key][] = $node;
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

    /** @param array<string, string> $variables */
    private function routeId(array $variables): int
    {
        $id = $variables['id'] ?? '';
        if (!ctype_digit($id) || (int) $id < 1) throw new RuntimeException('Некорректный ID узла.');
        return (int) $id;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) {
            throw new RuntimeException('Некорректный идентификатор связи.');
        }
        return (int) $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $context */
    private function audit(Request $request, string $action, ?int $entityId, array $context): void
    {
        $user = $this->auth->user();
        $ip = $request->server['REMOTE_ADDR'] ?? null;
        $this->audit->record(
            is_array($user) ? (int) $user['id'] : null,
            $action,
            'node',
            $entityId,
            $context,
            is_string($ip) ? $ip : null,
        );
    }
}
