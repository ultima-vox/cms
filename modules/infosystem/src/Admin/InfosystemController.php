<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem\Admin;

use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\AuditLogRepository;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\View\AdminPhpRenderer;
use RuntimeException;
use Throwable;
use UltimaVox\Modules\Infosystem\FieldSchema;
use UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository;

final class InfosystemController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly InfosystemManagementRepository $infosystems,
        private readonly FieldSchema $fieldSchema,
        private readonly AuditLogRepository $audit,
        private readonly AdminPhpRenderer $view,
    ) {
    }

    /** @param array<string, string> $variables */
    public function index(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        return Response::html($this->view->render('admin/infosystems/index.php', [
            'infosystems' => $this->infosystems->all(),
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

        return $this->systemFormResponse(null);
    }

    /** @param array<string, string> $variables */
    public function editForm(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        $system = $this->infosystems->find($this->routeId($variables, 'id'));
        if ($system === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        return $this->systemFormResponse($system, null, 200, isset($request->query['saved']));
    }

    /** @param array<string, string> $variables */
    public function store(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }

        try {
            $data = $this->systemData($request->post, null);
            $id = $this->infosystems->create(
                $data['name'],
                $data['code'],
                $data['description'],
                $data['field_schema'],
                $data['is_active'],
            );
            $this->audit($request, 'infosystem.create', 'infosystem', $id, ['code' => $data['code']]);
            return Response::redirect('/admin/infosystems/' . $id . '?created=1');
        } catch (Throwable $exception) {
            return $this->systemFormResponse($request->post, $exception->getMessage(), 422);
        }
    }

    /** @param array<string, string> $variables */
    public function update(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }

        $id = $this->routeId($variables, 'id');
        $existing = $this->infosystems->find($id);
        if ($existing === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        try {
            $data = $this->systemData($request->post, $existing);
            $this->infosystems->update(
                $id,
                $data['name'],
                $data['description'],
                $data['field_schema'],
                $data['is_active'],
            );
            $this->audit($request, 'infosystem.update', 'infosystem', $id, ['code' => $existing['code']]);
            return Response::redirect('/admin/infosystems/' . $id . '/edit?saved=1');
        } catch (Throwable $exception) {
            return $this->systemFormResponse(
                array_merge($existing, $request->post),
                $exception->getMessage(),
                422,
            );
        }
    }

    /** @param array<string, string> $variables */
    public function delete(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }

        $id = $this->routeId($variables, 'id');
        $system = $this->infosystems->find($id);
        if ($system === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        try {
            $this->infosystems->delete($id);
            $this->audit($request, 'infosystem.delete', 'infosystem', $id, ['code' => $system['code']]);
            return Response::redirect('/admin/infosystems?deleted=1');
        } catch (Throwable $exception) {
            return Response::html('<h1>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</h1>', 409);
        }
    }

    /** @param array<string, string> $variables */
    public function manage(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        $id = $this->routeId($variables, 'id');
        $system = $this->infosystems->find($id);
        if ($system === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        return Response::html($this->view->render('admin/infosystems/manage.php', [
            'infosystem' => $system,
            'groups' => $this->flattenGroups($this->infosystems->groups($id)),
            'items' => $this->infosystems->items($id),
            'csrf_token' => Csrf::token(),
            'created' => isset($request->query['created']),
            'saved' => isset($request->query['saved']),
            'deleted' => isset($request->query['deleted']),
        ]));
    }

    /** @param array<string, string> $variables */
    public function createGroupForm(Request $request, array $variables = []): Response
    {
        unset($request);
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        $infosystemId = $this->routeId($variables, 'id');
        $system = $this->requireSystem($infosystemId);
        if ($system instanceof Response) {
            return $system;
        }

        return $this->groupFormResponse($system, null);
    }

    /** @param array<string, string> $variables */
    public function editGroupForm(Request $request, array $variables = []): Response
    {
        unset($request);
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        $infosystemId = $this->routeId($variables, 'id');
        $system = $this->requireSystem($infosystemId);
        if ($system instanceof Response) {
            return $system;
        }
        $group = $this->infosystems->findGroup($infosystemId, $this->routeId($variables, 'groupId'));
        if ($group === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        return $this->groupFormResponse($system, $group);
    }

    /** @param array<string, string> $variables */
    public function storeGroup(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }
        $infosystemId = $this->routeId($variables, 'id');
        $system = $this->requireSystem($infosystemId);
        if ($system instanceof Response) {
            return $system;
        }

        try {
            $data = $this->groupData($request->post);
            $groupId = $this->infosystems->createGroup(
                $infosystemId,
                $data['parent_id'],
                $data['name'],
                $data['slug'],
                $data['description'],
                $data['sorting'],
                $data['is_active'],
            );
            $this->audit($request, 'infosystem_group.create', 'infosystem_group', $groupId, ['infosystem_id' => $infosystemId]);
            return Response::redirect('/admin/infosystems/' . $infosystemId . '?saved=1');
        } catch (Throwable $exception) {
            return $this->groupFormResponse($system, $request->post, $exception->getMessage(), 422);
        }
    }

    /** @param array<string, string> $variables */
    public function updateGroup(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }
        $infosystemId = $this->routeId($variables, 'id');
        $groupId = $this->routeId($variables, 'groupId');
        $system = $this->requireSystem($infosystemId);
        if ($system instanceof Response) {
            return $system;
        }
        $existing = $this->infosystems->findGroup($infosystemId, $groupId);
        if ($existing === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        try {
            $data = $this->groupData($request->post);
            $this->infosystems->updateGroup(
                $infosystemId,
                $groupId,
                $data['parent_id'],
                $data['name'],
                $data['slug'],
                $data['description'],
                $data['sorting'],
                $data['is_active'],
            );
            $this->audit($request, 'infosystem_group.update', 'infosystem_group', $groupId, ['infosystem_id' => $infosystemId]);
            return Response::redirect('/admin/infosystems/' . $infosystemId . '?saved=1');
        } catch (Throwable $exception) {
            return $this->groupFormResponse(
                $system,
                array_merge($existing, $request->post),
                $exception->getMessage(),
                422,
            );
        }
    }

    /** @param array<string, string> $variables */
    public function deleteGroup(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }
        $infosystemId = $this->routeId($variables, 'id');
        $groupId = $this->routeId($variables, 'groupId');

        try {
            $this->infosystems->deleteGroup($infosystemId, $groupId);
            $this->audit($request, 'infosystem_group.delete', 'infosystem_group', $groupId, ['infosystem_id' => $infosystemId]);
            return Response::redirect('/admin/infosystems/' . $infosystemId . '?deleted=1');
        } catch (Throwable $exception) {
            return Response::html('<h1>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</h1>', 409);
        }
    }

    /** @param array<string, string> $variables */
    public function createItemForm(Request $request, array $variables = []): Response
    {
        unset($request);
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        $infosystemId = $this->routeId($variables, 'id');
        $system = $this->requireSystem($infosystemId);
        if ($system instanceof Response) {
            return $system;
        }

        return $this->itemFormResponse($system, null);
    }

    /** @param array<string, string> $variables */
    public function editItemForm(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        $infosystemId = $this->routeId($variables, 'id');
        $system = $this->requireSystem($infosystemId);
        if ($system instanceof Response) {
            return $system;
        }
        $item = $this->infosystems->findItem($infosystemId, $this->routeId($variables, 'itemId'));
        if ($item === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        return $this->itemFormResponse($system, $item, null, 200, isset($request->query['saved']));
    }

    /** @param array<string, string> $variables */
    public function storeItem(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }
        $infosystemId = $this->routeId($variables, 'id');
        $system = $this->requireSystem($infosystemId);
        if ($system instanceof Response) {
            return $system;
        }

        try {
            $data = $this->itemData($request->post, $system);
            $itemId = $this->infosystems->saveItem(null, $infosystemId, $data['group_id'], $data);
            $this->audit($request, 'infosystem_item.create', 'infosystem_item', $itemId, ['infosystem_id' => $infosystemId]);
            return Response::redirect('/admin/infosystems/' . $infosystemId . '/items/' . $itemId . '/edit?saved=1');
        } catch (Throwable $exception) {
            return $this->itemFormResponse($system, $request->post, $exception->getMessage(), 422);
        }
    }

    /** @param array<string, string> $variables */
    public function updateItem(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }
        $infosystemId = $this->routeId($variables, 'id');
        $itemId = $this->routeId($variables, 'itemId');
        $system = $this->requireSystem($infosystemId);
        if ($system instanceof Response) {
            return $system;
        }
        $existing = $this->infosystems->findItem($infosystemId, $itemId);
        if ($existing === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        try {
            $data = $this->itemData($request->post, $system);
            $this->infosystems->saveItem($itemId, $infosystemId, $data['group_id'], $data);
            $this->audit($request, 'infosystem_item.update', 'infosystem_item', $itemId, ['infosystem_id' => $infosystemId]);
            return Response::redirect('/admin/infosystems/' . $infosystemId . '/items/' . $itemId . '/edit?saved=1');
        } catch (Throwable $exception) {
            return $this->itemFormResponse(
                $system,
                array_merge($existing, $request->post),
                $exception->getMessage(),
                422,
            );
        }
    }

    /** @param array<string, string> $variables */
    public function deleteItem(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }
        $infosystemId = $this->routeId($variables, 'id');
        $itemId = $this->routeId($variables, 'itemId');
        $this->infosystems->deleteItem($infosystemId, $itemId);
        $this->audit($request, 'infosystem_item.delete', 'infosystem_item', $itemId, ['infosystem_id' => $infosystemId]);
        return Response::redirect('/admin/infosystems/' . $infosystemId . '?deleted=1');
    }

    /** @param array<string, mixed>|null $system */
    private function systemFormResponse(?array $system, ?string $error = null, int $status = 200, bool $saved = false): Response
    {
        $form = array_merge([
            'id' => null,
            'name' => '',
            'code' => '',
            'description' => '',
            'field_schema' => [],
            'is_active' => true,
            'group_count' => 0,
            'item_count' => 0,
            'node_count' => 0,
        ], $system ?? []);

        if (isset($form['fields']) && is_array($form['fields'])) {
            $form['field_schema'] = $form['fields'];
        }

        return Response::html($this->view->render('admin/infosystems/form.php', [
            'infosystem' => $form,
            'csrf_token' => Csrf::token(),
            'error' => $error,
            'saved' => $saved,
        ]), $status);
    }

    /** @param array<string, mixed> $system @param array<string, mixed>|null $group */
    private function groupFormResponse(array $system, ?array $group, ?string $error = null, int $status = 200): Response
    {
        $form = array_merge([
            'id' => null,
            'parent_id' => null,
            'name' => '',
            'slug' => '',
            'description' => '',
            'sorting' => 0,
            'is_active' => true,
        ], $group ?? []);

        return Response::html($this->view->render('admin/infosystems/group_form.php', [
            'infosystem' => $system,
            'group' => $form,
            'groups' => $this->flattenGroups($this->infosystems->groups((int) $system['id'])),
            'csrf_token' => Csrf::token(),
            'error' => $error,
        ]), $status);
    }

    /** @param array<string, mixed> $system @param array<string, mixed>|null $item */
    private function itemFormResponse(
        array $system,
        ?array $item,
        ?string $error = null,
        int $status = 200,
        bool $saved = false,
    ): Response {
        $form = array_merge([
            'id' => null,
            'group_id' => null,
            'name' => '',
            'slug' => '',
            'path' => null,
            'description' => '',
            'content' => '',
            'meta_description' => '',
            'status' => 'draft',
            'is_active' => true,
            'sorting' => 0,
            'publish_at' => null,
            'properties' => [],
        ], $item ?? []);

        if (isset($item['properties']) && is_array($item['properties'])) {
            $form['properties'] = $item['properties'];
        }

        return Response::html($this->view->render('admin/infosystems/item_form.php', [
            'infosystem' => $system,
            'item' => $form,
            'fields' => is_array($system['field_schema'] ?? null) ? $system['field_schema'] : [],
            'groups' => $this->flattenGroups($this->infosystems->groups((int) $system['id'])),
            'csrf_token' => Csrf::token(),
            'error' => $error,
            'saved' => $saved,
        ]), $status);
    }

    /** @param array<string, mixed> $input @param array<string, mixed>|null $existing @return array<string, mixed> */
    private function systemData(array $input, ?array $existing): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 255) {
            throw new RuntimeException('Название инфосистемы обязательно и должно быть не длиннее 255 символов.');
        }

        $code = $existing === null
            ? strtolower(trim((string) ($input['code'] ?? '')))
            : (string) $existing['code'];
        if (!preg_match('/^[a-z][a-z0-9_-]{0,119}$/', $code)) {
            throw new RuntimeException('Код: a-z, 0-9, дефис или подчёркивание; первый символ — буква.');
        }

        $fields = $input['fields'] ?? [];
        if (!is_array($fields)) {
            throw new RuntimeException('Некорректная схема дополнительных полей.');
        }
        $fieldSchema = $this->fieldSchema->decode(json_encode(array_values($fields), JSON_THROW_ON_ERROR));

        return [
            'name' => $name,
            'code' => $code,
            'description' => $this->nullableText($input['description'] ?? null),
            'field_schema' => $fieldSchema,
            'is_active' => (string) ($input['is_active'] ?? '0') === '1',
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function groupData(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        if ($name === '') {
            throw new RuntimeException('Название группы обязательно.');
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,254}$/', $slug)) {
            throw new RuntimeException('Slug группы: только a-z, 0-9 и дефис.');
        }

        return [
            'parent_id' => $this->nullablePositiveInt($input['parent_id'] ?? null),
            'name' => $name,
            'slug' => $slug,
            'description' => $this->nullableText($input['description'] ?? null),
            'sorting' => max(0, (int) ($input['sorting'] ?? 0)),
            'is_active' => (string) ($input['is_active'] ?? '0') === '1',
        ];
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $system @return array<string, mixed> */
    private function itemData(array $input, array $system): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        if ($name === '') {
            throw new RuntimeException('Название элемента обязательно.');
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,254}$/', $slug)) {
            throw new RuntimeException('Slug элемента: только a-z, 0-9 и дефис.');
        }

        $status = (string) ($input['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            throw new RuntimeException('Некорректный статус элемента.');
        }

        $propertiesInput = $input['properties'] ?? [];
        if (!is_array($propertiesInput)) {
            throw new RuntimeException('Некорректные дополнительные свойства.');
        }
        $schema = is_array($system['field_schema'] ?? null) ? $system['field_schema'] : [];
        $properties = $this->fieldSchema->normalizeProperties($schema, $propertiesInput);
        $publishAt = trim((string) ($input['publish_at'] ?? ''));

        return [
            'group_id' => $this->nullablePositiveInt($input['group_id'] ?? null),
            'name' => $name,
            'slug' => $slug,
            'description' => $this->nullableText($input['description'] ?? null),
            'content' => (string) ($input['content'] ?? ''),
            'meta_description' => $this->nullableText($input['meta_description'] ?? null),
            'status' => $status,
            'is_active' => (string) ($input['is_active'] ?? '0') === '1',
            'sorting' => max(0, (int) ($input['sorting'] ?? 0)),
            'properties' => $properties,
            'publish_at' => $publishAt === '' ? null : $publishAt,
        ];
    }

    /** @return array<string, mixed>|Response */
    private function requireSystem(int $id): array|Response
    {
        $system = $this->infosystems->find($id);
        return $system ?? Response::html('<h1>404 Not Found</h1>', 404);
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

    private function validateCsrf(Request $request): ?Response
    {
        $value = $request->post['_csrf'] ?? null;
        if (!Csrf::validate(is_string($value) ? $value : null)) {
            return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
        }
        return null;
    }

    /** @param array<string, string> $variables */
    private function routeId(array $variables, string $key): int
    {
        $id = $variables[$key] ?? '';
        if (!ctype_digit($id) || (int) $id < 1) {
            throw new RuntimeException('Некорректный идентификатор маршрута.');
        }
        return (int) $id;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) {
            throw new RuntimeException('Некорректный идентификатор связи.');
        }
        return (int) $value;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    /** @param list<array<string, mixed>> $groups @return list<array<string, mixed>> */
    private function flattenGroups(array $groups): array
    {
        $children = [];
        foreach ($groups as $group) {
            $key = $group['parent_id'] === null ? 0 : (int) $group['parent_id'];
            $children[$key][] = $group;
        }

        $result = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$result, $children): void {
            foreach ($children[$parentId] ?? [] as $group) {
                $group['depth'] = $depth;
                $result[] = $group;
                $walk((int) $group['id'], $depth + 1);
            }
        };
        $walk(0, 0);
        return $result;
    }

    /** @param array<string, mixed> $context */
    private function audit(
        Request $request,
        string $action,
        string $entityType,
        ?int $entityId,
        array $context,
    ): void {
        $user = $this->auth->user();
        $ip = $request->server['REMOTE_ADDR'] ?? null;
        $this->audit->record(
            is_array($user) ? (int) $user['id'] : null,
            $action,
            $entityType,
            $entityId,
            $context,
            is_string($ip) ? $ip : null,
        );
    }
}
