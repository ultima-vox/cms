<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem\Admin;

use Core\Http\Request;
use Core\Http\Response;
use Core\Security\AuthService;
use Core\View\AdminPhpRenderer;
use RuntimeException;
use UltimaVox\Modules\Infosystem\Repository\InfosystemItemSearchRepository;
use UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository;

final class InfosystemItemListController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly InfosystemManagementRepository $infosystems,
        private readonly InfosystemItemSearchRepository $search,
        private readonly AdminPhpRenderer $view,
    ) {
    }

    /** @param array<string, string> $variables */
    public function overview(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        $infosystemId = $this->routeId($variables, 'id');
        $system = $this->infosystems->find($infosystemId);
        if ($system === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        $items = $this->search->search($infosystemId, 1, 25);

        return Response::html($this->view->render('admin/infosystems/manage.php', [
            'infosystem' => $system,
            'groups' => $this->flattenGroups($this->infosystems->groups($infosystemId)),
            'items' => $items['items'],
            'item_total' => $items['total'],
            'created' => isset($request->query['created']),
            'saved' => isset($request->query['saved']),
            'deleted' => isset($request->query['deleted']),
        ]));
    }

    /** @param array<string, string> $variables */
    public function index(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        $infosystemId = $this->routeId($variables, 'id');
        $system = $this->infosystems->find($infosystemId);
        if ($system === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        $page = $this->positiveInt($request->query['page'] ?? null, 1);
        $perPage = $this->positiveInt($request->query['per_page'] ?? null, 100);
        if (!in_array($perPage, [25, 50, 100, 200], true)) {
            $perPage = 100;
        }

        $query = $this->trimmedString($request->query['q'] ?? null);
        $groupId = $this->nullablePositiveInt($request->query['group_id'] ?? null);
        $status = $this->trimmedString($request->query['status'] ?? null);
        if ($status === '') {
            $status = null;
        }

        $schema = is_array($system['field_schema'] ?? null) ? $system['field_schema'] : [];
        $propertyFilters = $this->propertyFilters($schema, $request->query['properties'] ?? null);

        $result = $this->search->search(
            $infosystemId,
            $page,
            $perPage,
            $query,
            $groupId,
            $status,
            $propertyFilters,
        );

        $paginationQuery = http_build_query(array_filter([
            'q' => $query,
            'group_id' => $groupId,
            'status' => $status,
            'per_page' => $result['per_page'],
            'properties' => $propertyFilters,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []));

        return Response::html($this->view->render('admin/infosystems/items.php', [
            'infosystem' => $system,
            'groups' => $this->infosystems->groups($infosystemId),
            'items' => $result['items'],
            'pagination' => [
                'page' => $result['page'],
                'pages' => $result['pages'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'query' => $paginationQuery,
            ],
            'filters' => [
                'q' => $query ?? '',
                'group_id' => $groupId,
                'status' => $status ?? '',
                'properties' => $propertyFilters,
            ],
            'filterable_fields' => array_values(array_filter(
                $schema,
                static fn (mixed $field): bool => is_array($field) && (bool) ($field['filterable'] ?? false),
            )),
        ]));
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

    /**
     * @param list<array<string, mixed>> $schema
     * @return array<string, scalar>
     */
    private function propertyFilters(array $schema, mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $allowed = [];
        foreach ($schema as $field) {
            if (!is_array($field) || !(bool) ($field['filterable'] ?? false)) {
                continue;
            }
            $code = (string) ($field['code'] ?? '');
            if ($code !== '') {
                $allowed[$code] = $field;
            }
        }

        $filters = [];
        foreach ($raw as $code => $value) {
            if (!is_string($code) || !isset($allowed[$code]) || !is_scalar($value)) {
                continue;
            }
            $string = trim((string) $value);
            if ($string === '') {
                continue;
            }

            $type = (string) ($allowed[$code]['type'] ?? 'text');
            $filters[$code] = match ($type) {
                'number' => is_numeric($string)
                    ? (float) $string
                    : throw new RuntimeException(sprintf('Фильтр «%s» должен быть числом.', $allowed[$code]['name'] ?? $code)),
                'boolean' => in_array($string, ['1', 'true', 'yes'], true),
                default => $string,
            };
        }

        return $filters;
    }

    /** @param list<array<string, mixed>> $groups @return list<array<string, mixed>> */
    private function flattenGroups(array $groups): array
    {
        foreach ($groups as &$group) {
            $path = trim((string) ($group['path'] ?? ''), '/');
            $group['depth'] = $path === '' ? 0 : substr_count($path, '/');
        }
        unset($group);

        return $groups;
    }

    /** @param array<string, string> $variables */
    private function routeId(array $variables, string $key): int
    {
        $value = $variables[$key] ?? '';
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new RuntimeException('Некорректный идентификатор.');
        }
        return (int) $value;
    }

    private function positiveInt(mixed $value, int $default): int
    {
        if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) {
            return $default;
        }
        return (int) $value;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->positiveInt($value, 0) ?: null;
    }

    private function trimmedString(mixed $value): ?string
    {
        return is_scalar($value) ? trim((string) $value) : null;
    }
}
