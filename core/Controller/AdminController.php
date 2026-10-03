<?php

declare(strict_types=1);

namespace Core\Controller;

use Core\Extension\Api\AdminApi;
use Core\Extension\Api\AdminNavigationItem;
use Core\Extension\Api\SitesApi;
use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\AuditLogRepository;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\Version;
use Core\View\AdminPhpRenderer;

final class AdminController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AdminPhpRenderer $view,
        private readonly AdminApi $admin,
        private readonly SitesApi $sites,
        private readonly AuditLogRepository $audit,
    ) {
    }

    /** @param array<string, string> $variables */
    public function index(Request $request, array $variables = []): Response
    {
        unset($request, $variables);

        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->can('admin.access')) {
            return Response::html('<h1>403 Forbidden</h1>', 403);
        }

        $navigation = array_values(array_filter(
            $this->admin->items(),
            fn (AdminNavigationItem $item): bool => $item->permission === null || $this->auth->can($item->permission),
        ));

        return Response::html($this->view->render('admin/index.php', [
            'csrf_token' => Csrf::token(),
            'user' => $user,
            'navigation' => $navigation,
            'admin_site' => $this->sites->admin(),
            'admin_sites' => $this->sites->active(),
            'activity' => $this->audit->recent(8),
            'cms_version' => Version::STRING,
            'php_version' => PHP_VERSION,
        ]));
    }
}
