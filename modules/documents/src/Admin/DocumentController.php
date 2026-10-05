<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents\Admin;

use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\AuditLogRepository;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\View\AdminPhpRenderer;
use RuntimeException;
use Throwable;
use UltimaVox\Modules\Documents\Repository\DocumentManagementRepository;

final class DocumentController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly DocumentManagementRepository $documents,
        private readonly DocumentAdminService $service,
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

        return Response::html($this->view->render('admin/documents/index.php', [
            'documents' => $this->documents->all(),
            'created' => isset($request->query['created']),
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
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        $id = $this->routeId($variables);
        $document = $this->documents->find($id);
        if ($document === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        return $this->formResponse(
            $document,
            null,
            200,
            isset($request->query['saved']),
            isset($request->query['published']),
        );
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
            $data = $this->formData($request->post, true);
            $id = $this->service->create(
                $data['code'],
                $data['name'],
                $data['content'],
                $data['publish'],
            );
            $this->audit($request, 'document.create', $id, [
                'code' => $data['code'],
                'published' => $data['publish'],
            ]);

            return Response::redirect(
                '/admin/documents/' . $id . '/edit?' . ($data['publish'] ? 'published=1' : 'saved=1'),
            );
        } catch (Throwable $exception) {
            return $this->formResponse($request->post, $exception->getMessage(), 422);
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

        $id = $this->routeId($variables);
        $existing = $this->documents->find($id);
        if ($existing === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        try {
            $data = $this->formData($request->post, false);
            $versionId = $this->service->save(
                $id,
                $data['name'],
                $data['content'],
                $data['publish'],
            );
            $this->audit($request, 'document.version.create', $id, [
                'code' => $existing['code'],
                'version_id' => $versionId,
                'published' => $data['publish'],
            ]);

            return Response::redirect(
                '/admin/documents/' . $id . '/edit?' . ($data['publish'] ? 'published=1' : 'saved=1'),
            );
        } catch (Throwable $exception) {
            return $this->formResponse(
                array_merge($existing, $request->post),
                $exception->getMessage(),
                422,
            );
        }
    }

    /** @param array<string, mixed>|null $document */
    private function formResponse(
        ?array $document,
        ?string $error = null,
        int $status = 200,
        bool $saved = false,
        bool $published = false,
    ): Response {
        $documentId = isset($document['id']) && is_numeric($document['id'])
            ? (int) $document['id']
            : null;
        $editable = $documentId !== null ? $this->documents->editableVersion($documentId) : null;
        $versions = $documentId !== null ? $this->documents->versions($documentId) : [];

        $form = array_merge([
            'id' => null,
            'code' => '',
            'name' => '',
            'content' => $editable['content'] ?? '',
        ], $document ?? []);
        if (array_key_exists('content', $document ?? [])) {
            $form['content'] = (string) $document['content'];
        }

        return Response::html($this->view->render('admin/documents/form.php', [
            'document' => $form,
            'editable_version' => $editable,
            'versions' => $versions,
            'csrf_token' => Csrf::token(),
            'error' => $error,
            'saved' => $saved,
            'published' => $published,
        ]), $status);
    }

    /** @param array<string, mixed> $input @return array{code:string,name:string,content:string,publish:bool} */
    private function formData(array $input, bool $requireCode): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Название документа обязательно.');
        }

        $code = strtolower(trim((string) ($input['code'] ?? '')));
        if ($requireCode && !preg_match('/^[a-z][a-z0-9_-]{0,119}$/', $code)) {
            throw new RuntimeException('Код: a-z, 0-9, дефис и подчёркивание; первый символ — буква.');
        }

        $mode = (string) ($input['save_mode'] ?? 'draft');
        if (!in_array($mode, ['draft', 'publish'], true)) {
            throw new RuntimeException('Некорректный режим сохранения документа.');
        }

        return [
            'code' => $code,
            'name' => $name,
            'content' => (string) ($input['content'] ?? ''),
            'publish' => $mode === 'publish',
        ];
    }

    private function requirePermission(): ?Response
    {
        if ($this->auth->user() === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->can('documents.manage')) {
            return Response::html('<h1>403 Forbidden</h1>', 403);
        }

        return null;
    }

    private function validateCsrf(Request $request): ?Response
    {
        $token = $request->post['_csrf'] ?? null;
        if (!is_string($token) || !Csrf::validate($token)) {
            return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
        }

        return null;
    }

    /** @param array<string, string> $variables */
    private function routeId(array $variables): int
    {
        $id = $variables['id'] ?? '';
        if (!ctype_digit($id) || (int) $id < 1) {
            throw new RuntimeException('Некорректный ID документа.');
        }

        return (int) $id;
    }

    /** @param array<string, mixed> $context */
    private function audit(Request $request, string $action, int $entityId, array $context): void
    {
        $user = $this->auth->user();
        $ip = $request->server['REMOTE_ADDR'] ?? null;
        $this->audit->record(
            is_array($user) ? (int) $user['id'] : null,
            $action,
            'document',
            $entityId,
            $context,
            is_string($ip) ? $ip : null,
        );
    }
}
