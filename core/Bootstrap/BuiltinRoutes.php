<?php

declare(strict_types=1);

namespace Core\Bootstrap;

use Core\Controller\AdminController;
use Core\Controller\AuthController;
use Core\Controller\HealthController;
use Core\Controller\InfosystemController;
use Core\Controller\InfosystemItemListController;
use Core\Controller\LayoutController;
use Core\Controller\NodeController;
use Core\Controller\StructureController;
use Core\Extension\Api\RoutesApi;

final readonly class BuiltinRoutes
{
    public function __construct(
        private AuthController $authController,
        private AdminController $adminController,
        private StructureController $structureController,
        private LayoutController $layoutController,
        private InfosystemController $infosystemController,
        private InfosystemItemListController $infosystemItemListController,
        private HealthController $healthController,
        private NodeController $nodeController,
    ) {
    }

    public function register(RoutesApi $routes): void
    {
        $routes->get('/health', 'health.index', [$this->healthController, 'index']);
        $routes->get('/admin/login', 'auth.form', [$this->authController, 'form']);
        $routes->post('/admin/login', 'auth.login', [$this->authController, 'login']);
        $routes->post('/admin/logout', 'auth.logout', [$this->authController, 'logout']);
        $routes->get('/admin', 'admin.index', [$this->adminController, 'index']);

        $routes->get('/admin/structure', 'structure.index', [$this->structureController, 'index']);
        $routes->get('/admin/structure/create', 'structure.create', [$this->structureController, 'createForm']);
        $routes->post('/admin/structure', 'structure.store', [$this->structureController, 'store']);
        $routes->post('/admin/structure/reorder', 'structure.reorder', [$this->structureController, 'reorder']);
        $routes->get('/admin/structure/{id:\\d+}/edit', 'structure.edit', [$this->structureController, 'editForm']);
        $routes->post('/admin/structure/{id:\\d+}', 'structure.update', [$this->structureController, 'update']);
        $routes->post('/admin/structure/{id:\\d+}/delete', 'structure.delete', [$this->structureController, 'delete']);

        $routes->get('/admin/layouts', 'layout.index', [$this->layoutController, 'index']);
        $routes->get('/admin/layouts/create', 'layout.create', [$this->layoutController, 'createForm']);
        $routes->post('/admin/layouts', 'layout.store', [$this->layoutController, 'store']);
        $routes->get('/admin/layouts/{id:\\d+}/edit', 'layout.edit', [$this->layoutController, 'editForm']);
        $routes->post('/admin/layouts/{id:\\d+}', 'layout.update', [$this->layoutController, 'update']);
        $routes->post('/admin/layouts/{id:\\d+}/reset', 'layout.reset', [$this->layoutController, 'reset']);
        $routes->post('/admin/layouts/{id:\\d+}/delete', 'layout.delete', [$this->layoutController, 'delete']);

        $routes->get('/admin/infosystems', 'infosystem.index', [$this->infosystemController, 'index']);
        $routes->get('/admin/infosystems/create', 'infosystem.create', [$this->infosystemController, 'createForm']);
        $routes->post('/admin/infosystems', 'infosystem.store', [$this->infosystemController, 'store']);
        $routes->get('/admin/infosystems/{id:\\d+}', 'infosystem.manage', [$this->infosystemItemListController, 'overview']);
        $routes->get('/admin/infosystems/{id:\\d+}/edit', 'infosystem.edit', [$this->infosystemController, 'editForm']);
        $routes->post('/admin/infosystems/{id:\\d+}', 'infosystem.update', [$this->infosystemController, 'update']);
        $routes->post('/admin/infosystems/{id:\\d+}/delete', 'infosystem.delete', [$this->infosystemController, 'delete']);

        $routes->get('/admin/infosystems/{id:\\d+}/groups/create', 'infosystem.group.create', [$this->infosystemController, 'createGroupForm']);
        $routes->post('/admin/infosystems/{id:\\d+}/groups', 'infosystem.group.store', [$this->infosystemController, 'storeGroup']);
        $routes->get('/admin/infosystems/{id:\\d+}/groups/{groupId:\\d+}/edit', 'infosystem.group.edit', [$this->infosystemController, 'editGroupForm']);
        $routes->post('/admin/infosystems/{id:\\d+}/groups/{groupId:\\d+}', 'infosystem.group.update', [$this->infosystemController, 'updateGroup']);
        $routes->post('/admin/infosystems/{id:\\d+}/groups/{groupId:\\d+}/delete', 'infosystem.group.delete', [$this->infosystemController, 'deleteGroup']);

        $routes->get('/admin/infosystems/{id:\\d+}/items', 'infosystem.item.index', [$this->infosystemItemListController, 'index']);
        $routes->get('/admin/infosystems/{id:\\d+}/items/create', 'infosystem.item.create', [$this->infosystemController, 'createItemForm']);
        $routes->post('/admin/infosystems/{id:\\d+}/items', 'infosystem.item.store', [$this->infosystemController, 'storeItem']);
        $routes->get('/admin/infosystems/{id:\\d+}/items/{itemId:\\d+}/edit', 'infosystem.item.edit', [$this->infosystemController, 'editItemForm']);
        $routes->post('/admin/infosystems/{id:\\d+}/items/{itemId:\\d+}', 'infosystem.item.update', [$this->infosystemController, 'updateItem']);
        $routes->post('/admin/infosystems/{id:\\d+}/items/{itemId:\\d+}/delete', 'infosystem.item.delete', [$this->infosystemController, 'deleteItem']);
    }

    public function registerPublicFallback(RoutesApi $routes): void
    {
        $routes->get('/', 'node.resolve', [$this->nodeController, 'resolve']);
        $routes->get('/{path:.+}', 'node.resolve.path', [$this->nodeController, 'resolve']);
    }
}
