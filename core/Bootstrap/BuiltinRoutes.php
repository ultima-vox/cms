<?php

declare(strict_types=1);

namespace Core\Bootstrap;

use Core\Content\HtmlSanitizingHandler;
use Core\Controller\AdminController;
use Core\Controller\AuthController;
use Core\Controller\HealthController;
use Core\Controller\LayoutController;
use Core\Controller\NodeController;
use Core\Controller\StructureController;
use Core\Extension\Api\RoutesApi;
use Core\Security\PermissionGate;

final readonly class BuiltinRoutes
{
    public function __construct(
        private AuthController $authController,
        private AdminController $adminController,
        private StructureController $structureController,
        private LayoutController $layoutController,
        private HealthController $healthController,
        private NodeController $nodeController,
        private PermissionGate $permissionGate,
        private HtmlSanitizingHandler $html,
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

        $routes->get('/admin/structure', 'structure.index', [$this->structureController, 'index']);
        $routes->get('/admin/structure/create', 'structure.create', [$this->structureController, 'createForm']);
        $routes->post(
            '/admin/structure',
            'structure.store',
            $this->permissionGate->require(
                'structure.manage',
                $this->html->wrap([$this->structureController, 'store'], ['content' => 'rich']),
            ),
        );
        $routes->post('/admin/structure/reorder', 'structure.reorder', [$this->structureController, 'reorder']);
        $routes->get('/admin/structure/{id:\\d+}/edit', 'structure.edit', [$this->structureController, 'editForm']);
        $routes->post(
            '/admin/structure/{id:\\d+}',
            'structure.update',
            $this->permissionGate->require(
                'structure.manage',
                $this->html->wrap([$this->structureController, 'update'], ['content' => 'rich']),
            ),
        );
        $routes->post('/admin/structure/{id:\\d+}/delete', 'structure.delete', [$this->structureController, 'delete']);

        $routes->get('/admin/layouts', 'layout.index', [$this->layoutController, 'index']);
        $routes->get('/admin/layouts/create', 'layout.create', $this->permissionGate->require('templates.code.edit', [$this->layoutController, 'createForm']));
        $routes->post('/admin/layouts', 'layout.store', $this->permissionGate->require('templates.code.edit', [$this->layoutController, 'store']));
        $routes->get('/admin/layouts/{id:\\d+}/edit', 'layout.edit', $this->permissionGate->require('templates.code.edit', [$this->layoutController, 'editForm']));
        $routes->post('/admin/layouts/{id:\\d+}', 'layout.update', $this->permissionGate->require('templates.code.edit', [$this->layoutController, 'update']));
        $routes->post('/admin/layouts/{id:\\d+}/reset', 'layout.reset', $this->permissionGate->require('templates.code.edit', [$this->layoutController, 'reset']));
        $routes->post('/admin/layouts/{id:\\d+}/delete', 'layout.delete', $this->permissionGate->require('templates.code.edit', [$this->layoutController, 'delete']));
    }

    public function registerPublicFallback(RoutesApi $routes): void
    {
        $routes->get('/', 'node.resolve', [$this->nodeController, 'resolve']);
        $routes->get('/{path:.+}', 'node.resolve.path', [$this->nodeController, 'resolve']);
    }
}
