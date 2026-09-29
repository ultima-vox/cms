<?php

declare(strict_types=1);

namespace Core\Controller;

use Core\Http\Request;
use Core\Http\Response;
use Core\Layout\LayoutTemplateService;
use Core\Repository\AuditLogRepository;
use Core\Repository\LayoutRepository;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\View\TwigRenderer;
use RuntimeException;
use Throwable;

final class LayoutController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly LayoutRepository $layouts,
        private readonly LayoutTemplateService $templates,
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

        return Response::html($this->view->render('admin/layouts/index.twig', [
            'layouts' => $this->layouts->all(),
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

        return $this->formResponse($this->normalizeFormModel([
            'source' => $this->defaultTemplateSource(),
        ]));
    }

    /** @param array<string, string> $variables */
    public function editForm(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        $layout = $this->findLayout($variables);
        if ($layout === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        try {
            $templatePath = (string) $layout['template_path'];
            $layout['source'] = $this->templates->read($templatePath);
            $layout['has_override'] = $this->templates->hasOverride($templatePath);

            return $this->formResponse(
                $this->normalizeFormModel($layout),
                null,
                200,
                isset($request->query['saved'])
                    || isset($request->query['created'])
                    || isset($request->query['reset']),
            );
        } catch (Throwable $exception) {
            return $this->formResponse($this->normalizeFormModel($layout), $exception->getMessage(), 500);
        }
    }

    /** @param array<string, string> $variables */
    public function store(Request $request, array $variables = []): Response
    {
        unset($variables);

        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (!Csrf::validate($this->stringOrNull($request->post['_csrf'] ?? null))) {
            return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
        }

        $model = $this->normalizeFormModel($request->post);

        try {
            $name = $this->requiredName($model['name']);
            $templatePath = $this->templates->templatePathForCode((string) $model['code']);
            $source = (string) $model['source'];

            if ($this->layouts->isTemplatePathUsed($templatePath)) {
                throw new RuntimeException('Макет с таким кодом уже существует.');
            }

            $this->templates->create($templatePath, $source);

            try {
                $id = $this->layouts->create(
                    $name,
                    $templatePath,
                    $this->nullableText($model['description']),
                );
            } catch (Throwable $exception) {
                $this->templates->delete($templatePath);
                throw $exception;
            }

            $this->audit($request, 'layout.create', $id, ['template_path' => $templatePath]);

            return Response::redirect('/admin/layouts/' . $id . '/edit?created=1');
        } catch (Throwable $exception) {
            return $this->formResponse($model, $exception->getMessage(), 422);
        }
    }

    /** @param array<string, string> $variables */
    public function update(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (!Csrf::validate($this->stringOrNull($request->post['_csrf'] ?? null))) {
            return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
        }

        $layout = $this->findLayout($variables);
        if ($layout === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        $model = $this->normalizeFormModel(array_merge($layout, $request->post));
        $model['code'] = $this->codeFromTemplatePath((string) $layout['template_path']);
        $model['template_path'] = (string) $layout['template_path'];

        try {
            $this->templates->update((string) $layout['template_path'], (string) $model['source']);
            $this->layouts->update(
                (int) $layout['id'],
                $this->requiredName($model['name']),
                $this->nullableText($model['description']),
            );
            $this->audit($request, 'layout.update', (int) $layout['id'], [
                'template_path' => $layout['template_path'],
            ]);

            return Response::redirect('/admin/layouts/' . $layout['id'] . '/edit?saved=1');
        } catch (Throwable $exception) {
            $model['has_override'] = $this->templates->hasOverride((string) $layout['template_path']);

            return $this->formResponse($model, $exception->getMessage(), 422);
        }
    }

    /** @param array<string, string> $variables */
    public function reset(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (!Csrf::validate($this->stringOrNull($request->post['_csrf'] ?? null))) {
            return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
        }

        $layout = $this->findLayout($variables);
        if ($layout === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }
        if (!(bool) $layout['is_system']) {
            return Response::html('<h1>Сброс доступен только для системного макета.</h1>', 409);
        }

        try {
            $this->templates->resetOverride((string) $layout['template_path']);
            $this->audit($request, 'layout.reset', (int) $layout['id'], [
                'template_path' => $layout['template_path'],
            ]);

            return Response::redirect('/admin/layouts/' . $layout['id'] . '/edit?reset=1');
        } catch (Throwable $exception) {
            return Response::html(
                '<h1>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</h1>',
                500,
            );
        }
    }

    /** @param array<string, string> $variables */
    public function delete(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (!Csrf::validate($this->stringOrNull($request->post['_csrf'] ?? null))) {
            return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
        }

        $layout = $this->findLayout($variables);
        if ($layout === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }
        if ((bool) $layout['is_system']) {
            return Response::html('<h1>Системный макет удалить нельзя.</h1>', 409);
        }
        if ((int) $layout['node_count'] > 0) {
            return Response::html('<h1>Макет используется узлами сайта.</h1>', 409);
        }

        try {
            $this->layouts->delete((int) $layout['id']);
            $this->templates->delete((string) $layout['template_path']);
            $this->audit($request, 'layout.delete', (int) $layout['id'], [
                'template_path' => $layout['template_path'],
            ]);

            return Response::redirect('/admin/layouts?deleted=1');
        } catch (Throwable) {
            return Response::html('<h1>Не удалось удалить макет.</h1>', 500);
        }
    }

    /** @param array<string, string> $variables @return array<string, mixed>|null */
    private function findLayout(array $variables): ?array
    {
        $id = $variables['id'] ?? '';

        return ctype_digit($id) && (int) $id > 0
            ? $this->layouts->find((int) $id)
            : null;
    }

    /** @param array<string, mixed> $model */
    private function formResponse(
        array $model,
        ?string $error = null,
        int $status = 200,
        bool $saved = false,
    ): Response {
        return Response::html($this->view->render('admin/layouts/form.twig', [
            'layout' => $this->normalizeFormModel($model),
            'csrf_token' => Csrf::token(),
            'error' => $error,
            'saved' => $saved,
        ]), $status);
    }

    private function requirePermission(): ?Response
    {
        if ($this->auth->user() === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->can('layouts.manage')) {
            return Response::html('<h1>403 Forbidden</h1>', 403);
        }

        return null;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function normalizeFormModel(array $input): array
    {
        $templatePath = isset($input['template_path']) ? (string) $input['template_path'] : '';

        return [
            'id' => isset($input['id']) && is_numeric($input['id']) ? (int) $input['id'] : null,
            'name' => isset($input['name']) ? (string) $input['name'] : '',
            'code' => isset($input['code']) ? (string) $input['code'] : $this->codeFromTemplatePath($templatePath),
            'template_path' => $templatePath,
            'description' => isset($input['description']) ? (string) $input['description'] : '',
            'source' => isset($input['source']) ? (string) $input['source'] : '',
            'is_system' => isset($input['is_system']) && (bool) $input['is_system'],
            'node_count' => isset($input['node_count']) ? (int) $input['node_count'] : 0,
            'has_override' => isset($input['has_override']) && (bool) $input['has_override'],
        ];
    }

    private function requiredName(mixed $value): string
    {
        $name = trim((string) $value);
        $length = preg_match_all('/./u', $name);

        if ($name === '' || $length === false || $length > 255) {
            throw new RuntimeException('Название макета обязательно и должно быть не длиннее 255 символов.');
        }

        return $name;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function codeFromTemplatePath(string $templatePath): string
    {
        return preg_match('#^layouts/([a-z0-9][a-z0-9_-]{0,79})\.(?:html\.php|twig)$#', $templatePath, $matches)
            ? $matches[1]
            : '';
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function defaultTemplateSource(): string
    {
        return <<<'PHP_TEMPLATE'
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= text($page->title) ?></title>
</head>
<body>
    <main>
        <h1><?= text($page->title) ?></h1>
        <?= html($page->content) ?>
    </main>
</body>
</html>
PHP_TEMPLATE;
    }

    /** @param array<string, mixed> $context */
    private function audit(Request $request, string $action, ?int $entityId, array $context): void
    {
        $user = $this->auth->user();
        $ip = $request->server['REMOTE_ADDR'] ?? null;

        $this->audit->record(
            is_array($user) ? (int) $user['id'] : null,
            $action,
            'layout',
            $entityId,
            $context,
            is_string($ip) ? $ip : null,
        );
    }
}
