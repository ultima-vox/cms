<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Sites;

use Core\Http\Request;
use Core\Http\Response;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\Site\AdminSiteSelector;
use Core\Site\SiteResolver;
use Core\View\AdminPhpRenderer;
use PDOException;
use RuntimeException;
use Throwable;

final class SitesController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly SitesManagementRepository $sites,
        private readonly AdminSiteSelector $selector,
        private readonly SiteResolver $hostPolicy,
        private readonly AdminPhpRenderer $view,
    ) {
    }

    /** @param array<string, string> $variables */
    public function index(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requirePermission('sites.manage')) !== null) {
            return $denied;
        }

        return Response::html($this->view->render('admin/sites/index.php', [
            'sites' => $this->sites->all(),
            'admin_site' => $this->selector->current(),
            'csrf_token' => Csrf::token(),
            'saved' => isset($request->query['saved']),
        ]));
    }

    /** @param array<string, string> $variables */
    public function createForm(Request $request, array $variables = []): Response
    {
        unset($request, $variables);
        if (($denied = $this->requirePermission('sites.manage')) !== null) {
            return $denied;
        }

        return $this->formResponse(null);
    }

    /** @param array<string, string> $variables */
    public function store(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requirePermission('sites.manage')) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }

        try {
            $name = $this->name($request->post['name'] ?? null);
            $code = strtolower(trim((string) ($request->post['code'] ?? '')));
            if (preg_match('/^[a-z][a-z0-9_-]{0,119}$/D', $code) !== 1) {
                throw new RuntimeException('Код сайта: a-z, 0-9, дефис или подчёркивание; первый символ — буква.');
            }

            $id = $this->sites->create($name, $code);
            return Response::redirect('/admin/sites/' . $id . '/edit?saved=1');
        } catch (Throwable $exception) {
            return $this->formResponse($request->post, $this->message($exception), 422);
        }
    }

    /** @param array<string, string> $variables */
    public function editForm(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission('sites.manage')) !== null) {
            return $denied;
        }

        $id = $this->routeId($variables, 'id');
        $site = $this->sites->find($id);
        if ($site === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        return $this->formResponse($site, null, 200, isset($request->query['saved']));
    }

    /** @param array<string, string> $variables */
    public function update(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission('sites.manage')) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }

        $id = $this->routeId($variables, 'id');
        $existing = $this->sites->find($id);
        if ($existing === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        try {
            $this->sites->update(
                $id,
                $this->name($request->post['name'] ?? null),
                (string) ($request->post['is_active'] ?? '0') === '1',
            );
            return Response::redirect('/admin/sites/' . $id . '/edit?saved=1');
        } catch (Throwable $exception) {
            return $this->formResponse(array_merge($existing, $request->post), $this->message($exception), 422);
        }
    }

    /** @param array<string, string> $variables */
    public function addDomain(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission('sites.manage')) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }

        $siteId = $this->routeId($variables, 'id');
        $host = $this->hostPolicy->normalizeHost((string) ($request->post['host'] ?? ''));
        if ($host === null) {
            return Response::html('<h1>422 Некорректный домен</h1>', 422);
        }

        try {
            $this->sites->addDomain(
                $siteId,
                $host,
                (string) ($request->post['is_primary'] ?? '0') === '1',
            );
            return Response::redirect('/admin/sites/' . $siteId . '/edit?saved=1');
        } catch (Throwable $exception) {
            return Response::html('<h1>409 ' . htmlspecialchars($this->message($exception), ENT_QUOTES, 'UTF-8') . '</h1>', 409);
        }
    }

    /** @param array<string, string> $variables */
    public function makePrimary(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission('sites.manage')) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }

        $siteId = $this->routeId($variables, 'id');
        $domainId = $this->routeId($variables, 'domainId');
        $this->sites->makePrimary($siteId, $domainId);

        return Response::redirect('/admin/sites/' . $siteId . '/edit?saved=1');
    }

    /** @param array<string, string> $variables */
    public function deleteDomain(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requirePermission('sites.manage')) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }

        $siteId = $this->routeId($variables, 'id');
        $domainId = $this->routeId($variables, 'domainId');
        $this->sites->deleteDomain($siteId, $domainId);

        return Response::redirect('/admin/sites/' . $siteId . '/edit?saved=1');
    }

    /** @param array<string, string> $variables */
    public function select(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requirePermission('admin.access')) !== null) {
            return $denied;
        }
        if (($csrf = $this->validateCsrf($request)) !== null) {
            return $csrf;
        }

        $siteId = $this->positiveInt($request->post['site_id'] ?? null);
        if ($siteId === null) {
            return Response::html('<h1>422 Некорректный сайт</h1>', 422);
        }

        try {
            $this->selector->select($siteId);
        } catch (RuntimeException) {
            return Response::html('<h1>404 Site Not Found</h1>', 404);
        }

        return Response::redirect('/admin');
    }

    /** @param array<string, mixed>|null $site */
    private function formResponse(
        ?array $site,
        ?string $error = null,
        int $status = 200,
        bool $saved = false,
    ): Response {
        $form = array_merge([
            'id' => null,
            'name' => '',
            'code' => '',
            'is_active' => true,
        ], $site ?? []);

        $domains = isset($form['id']) && is_numeric($form['id'])
            ? $this->sites->domains((int) $form['id'])
            : [];

        return Response::html($this->view->render('admin/sites/form.php', [
            'site' => $form,
            'domains' => $domains,
            'admin_site' => $this->selector->current(),
            'csrf_token' => Csrf::token(),
            'error' => $error,
            'saved' => $saved,
        ]), $status);
    }

    private function requirePermission(string $permission): ?Response
    {
        if ($this->auth->user() === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->can($permission)) {
            return Response::html('<h1>403 Forbidden</h1>', 403);
        }
        return null;
    }

    private function validateCsrf(Request $request): ?Response
    {
        $token = $request->post['_csrf'] ?? null;
        return Csrf::validate(is_string($token) ? $token : null)
            ? null
            : Response::html('<h1>419 CSRF token mismatch</h1>', 419);
    }

    private function name(mixed $value): string
    {
        $name = trim((string) $value);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new RuntimeException('Название сайта обязательно и должно быть не длиннее 255 символов.');
        }
        return $name;
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

    private function positiveInt(mixed $value): ?int
    {
        return is_scalar($value) && ctype_digit((string) $value) && (int) $value > 0
            ? (int) $value
            : null;
    }

    private function message(Throwable $exception): string
    {
        if ($exception instanceof PDOException && $exception->getCode() === '23505') {
            return 'Такой код сайта или домен уже используется.';
        }

        return $exception->getMessage();
    }
}
