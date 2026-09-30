<?php

declare(strict_types=1);

namespace Core\Bootstrap;

use Core\Content\HtmlSanitizingHandler;
use Core\Controller\AdminController;
use Core\Controller\AuthController;
use Core\Controller\HealthController;
use Core\Controller\LayoutController;
use Core\Controller\ModuleManagerController;
use Core\Controller\NodeController;
use Core\Controller\StructureController;
use Core\Delivery\DeliveryInvalidatingHandler;
use Core\Extension\Api\RoutesApi;
use Core\Http\Request;
use Core\Security\PermissionGate;

final readonly class BuiltinRoutes
{
    public function __construct(
        private AuthController $authController,
        private AdminController $adminController,
        private StructureController $structureController,
        private LayoutController $layoutController,
        private ModuleManagerController $moduleManagerController,
        private HealthController $healthController,
        private NodeController $nodeController,
        private PermissionGate $permissionGate,
        private HtmlSanitizingHandler $html,
        private DeliveryInvalidatingHandler $invalidate,
        private int $adminSiteId,
    ) {
    }

    public function register(RoutesApi $routes): void
    {
        $routes->get('/health', 'health.index', [$this->healthController, 'index']);
        $routes->get('/health/live', 'health.live', [$this->healthController, 'live']);
        $routes->get('/health/ready', 'health.ready', [$this->healthController, 'ready']);

        $routes->get('/admin/login', 'auth.form', [$this->authController, 'form']);
        $routes->post('/admin/login', 'auth.login', [$this->authController, 'login']);
        $routes->post('/admin/logout', 'auth.logout', [$this->authController, 'logout']);
        $routes->get('/admin', 'admin.index', [$this->adminController, 'index']);

        $siteTags = fn (Request $request, array $variables): array => ['site:' . $this->adminSiteId];
        $layoutTags = static function (Request $request, array $variables): array {
            $id = $variables['id'] ?? '';
            return ctype_digit($id) ? ['layout:' . (int) $id] : [];
        };

        $routes->get('/admin/structure', 'structure.index', [$this->structureController, 'index']);
        $routes->get('/admin/structure/create', 'structure.create', [$this->structureController, 'createForm']);
        $routes->post(
            '/admin/structure',
            'structure.store',
            $this->permissionGate->require(
                'structure.manage',
                $this->invalidate->wrap(
                    $this->html->wrap([$this->structureController, 'store'], ['content' => 'rich']),
                    $siteTags,
                ),
            ),
        );
        $routes->post(
            '/admin/structure/reorder',
            'structure.reorder',
            $this->permissionGate->require(
                'structure.manage',
                $this->invalidate->wrap([$this->structureController, 'reorder'], $siteTags),
            ),
        );
        $routes->get('/admin/structure/{id:\\d+}/edit', 'structure.edit', [$this->structureController, 'editForm']);
        $routes->post(
            '/admin/structure/{id:\\d+}',
            'structure.update',
            $this->permissionGate->require(
                'structure.manage',
                $this->invalidate->wrap(
                    $this->html->wrap([$this->structureController, 'update'], ['content' => 'rich']),
                    $siteTags,
                ),
            ),
        );
        $routes->post(
            '/admin/structure/{id:\\d+}/delete',
            'structure.delete',
            $this->permissionGate->require(
                'structure.manage',
                $this->invalidate->wrap([$this->structureController, 'delete'], $siteTags),
            ),
        );

        $routes->get('/admin/layouts', 'layout.index', [$this->layoutController, 'index']);
        $routes->get('/admin/layouts/create', 'layout.create', $this->permissionGate->require('templates.code.edit', [$this->layoutController, 'createForm']));
        $routes->post('/admin/layouts', 'layout.store', $this->permissionGate->require('templates.code.edit', [$this->layoutController, 'store']));
        $routes->get('/admin/layouts/{id:\\d+}/edit', 'layout.edit', $this->permissionGate->require('templates.code.edit', [$this->layoutController, 'editForm']));
        $routes->post(
            '/admin/layouts/{id:\\d+}',
            'layout.update',
            $this->permissionGate->require(
                'templates.code.edit',
                $this->invalidate->wrap([$this->layoutController, 'update'], $layoutTags),
            ),
        );
        $routes->post(
            '/admin/layouts/{id:\\d+}/reset',
            'layout.reset',
            $this->permissionGate->require(
                'templates.code.edit',
                $this->invalidate->wrap([$this->layoutController, 'reset'], $layoutTags),
            ),
        );
        $routes->post(
            '/admin/layouts/{id:\\d+}/delete',
            'layout.delete',
            $this->permissionGate->require(
                'templates.code.edit',
                $this->invalidate->wrap([$this->layoutController, 'delete'], $layoutTags),
            ),
        );

        $moduleCodePattern = '[a-z][a-z0-9._-]{0,79}';
        $routes->get(
            '/admin/modules',
            'module.index',
            $this->permissionGate->require('modules.manage', [$this->moduleManagerController, 'index']),
        );
        $routes->post(
            '/admin/modules/sync',
            'module.sync',
            $this->permissionGate->require('modules.manage', [$this->moduleManagerController, 'sync']),
        );
        $routes->post(
            '/admin/modules/{code:' . $moduleCodePattern . '}/enable',
            'module.enable',
            $this->permissionGate->require('modules.manage', [$this->moduleManagerController, 'enable']),
        );
        $routes->post(
            '/admin/modules/{code:' . $moduleCodePattern . '}/disable',
            'module.disable',
            $this->permissionGate->require('modules.manage', [$this->moduleManagerController, 'disable']),
        );
        $routes->post(
            '/admin/modules/{code:' . $moduleCodePattern . '}/migrate',
            'module.migrate',
            $this->permissionGate->require('modules.manage', [$this->moduleManagerController, 'migrate']),
        );
        $routes->post(
            '/admin/modules/{code:' . $moduleCodePattern . '}/remove',
            'module.remove',
            $this->permissionGate->require('modules.manage', [$this->moduleManagerController, 'remove']),
        );
    }

    public function registerPublicFallback(RoutesApi $routes): void
    {
        $routes->get('/', 'node.resolve', [$this->nodeController, 'resolve']);
        $routes->get('/{path:.+}', 'node.resolve.path', [$this->nodeController, 'resolve']);
    }
}
