<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Controller\InfosystemController;
use Core\Controller\InfosystemItemListController;
use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\Infosystem\FieldSchema;
use Core\Repository\InfosystemItemSearchRepository;
use Core\Repository\InfosystemManagementRepository;
use Core\Repository\InfosystemRepository;
use Core\View\Render\RenderNodeInterface;
use Core\View\Render\TemplateFacadeContext;
use RuntimeException;

final class InfosystemModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $runtime = $core->runtime();
        $db = $runtime->database();
        $repository = new InfosystemRepository($db);
        $management = new InfosystemManagementRepository($db);
        $controller = new InfosystemController(
            $runtime->auth(),
            $management,
            new FieldSchema(),
            $runtime->audit(),
            $runtime->adminRenderer(),
        );
        $itemListController = new InfosystemItemListController(
            $runtime->auth(),
            $management,
            new InfosystemItemSearchRepository($db),
            $runtime->adminRenderer(),
        );
        $routes = $core->routes();
        $content = $core->content();
        $events = $core->events();

        $core->permissions()->define(
            'infosystems.manage',
            'Manage infosystems',
            ['superadmin', 'admin', 'editor'],
        );
        $core->admin()->navigation(
            'infosystems',
            'Инфосистемы',
            '/admin/infosystems',
            'infosystems.manage',
            30,
        );

        $routes->get('/admin/infosystems', 'infosystem.index', [$controller, 'index']);
        $routes->get('/admin/infosystems/create', 'infosystem.create', [$controller, 'createForm']);
        $routes->post('/admin/infosystems', 'infosystem.store', [$controller, 'store']);
        $routes->get('/admin/infosystems/{id:\\d+}', 'infosystem.manage', [$itemListController, 'overview']);
        $routes->get('/admin/infosystems/{id:\\d+}/edit', 'infosystem.edit', [$controller, 'editForm']);
        $routes->post('/admin/infosystems/{id:\\d+}', 'infosystem.update', [$controller, 'update']);
        $routes->post('/admin/infosystems/{id:\\d+}/delete', 'infosystem.delete', [$controller, 'delete']);

        $routes->get('/admin/infosystems/{id:\\d+}/groups/create', 'infosystem.group.create', [$controller, 'createGroupForm']);
        $routes->post('/admin/infosystems/{id:\\d+}/groups', 'infosystem.group.store', [$controller, 'storeGroup']);
        $routes->get('/admin/infosystems/{id:\\d+}/groups/{groupId:\\d+}/edit', 'infosystem.group.edit', [$controller, 'editGroupForm']);
        $routes->post('/admin/infosystems/{id:\\d+}/groups/{groupId:\\d+}', 'infosystem.group.update', [$controller, 'updateGroup']);
        $routes->post('/admin/infosystems/{id:\\d+}/groups/{groupId:\\d+}/delete', 'infosystem.group.delete', [$controller, 'deleteGroup']);

        $routes->get('/admin/infosystems/{id:\\d+}/items', 'infosystem.item.index', [$itemListController, 'index']);
        $routes->get('/admin/infosystems/{id:\\d+}/items/create', 'infosystem.item.create', [$controller, 'createItemForm']);
        $routes->post('/admin/infosystems/{id:\\d+}/items', 'infosystem.item.store', [$controller, 'storeItem']);
        $routes->get('/admin/infosystems/{id:\\d+}/items/{itemId:\\d+}/edit', 'infosystem.item.edit', [$controller, 'editItemForm']);
        $routes->post('/admin/infosystems/{id:\\d+}/items/{itemId:\\d+}', 'infosystem.item.update', [$controller, 'updateItem']);
        $routes->post('/admin/infosystems/{id:\\d+}/items/{itemId:\\d+}/delete', 'infosystem.item.delete', [$controller, 'deleteItem']);

        $content->source(
            'infosystem.items',
            static function (TemplateFacadeContext $context, array $options) use ($repository, $events): RenderNodeInterface {
                $infosystem = $options['infosystem'] ?? null;
                if (!is_array($infosystem) || !isset($infosystem['id'], $infosystem['code'])) {
                    throw new RuntimeException('Infosystem content source requires a resolved infosystem record.');
                }

                return new InfosystemItemsSource(
                    $context,
                    $repository,
                    $events,
                    $infosystem,
                );
            },
        );

        $core->templates()->view(
            'infosystem.list',
            'infosystem.items',
            'modules/infosystem/templates/list.html.php',
        );

        $core->templates()->facade(
            'infosystems',
            static fn (TemplateFacadeContext $context): InfosystemsFacade => new InfosystemsFacade(
                $repository,
                $content,
                $context,
            ),
        );

        $core->templates()->facadeProvider(
            static function (TemplateFacadeContext $context, array $facades): array {
                $registry = $facades['infosystems'] ?? null;
                if (!$registry instanceof InfosystemsFacade) {
                    return [];
                }

                $linked = $registry->linked();
                if ($linked === null) {
                    return [];
                }

                $code = $linked->code();
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,79}$/', $code)) {
                    return [];
                }
                if (array_key_exists($code, $context->variables())) {
                    return [];
                }
                if (in_array($code, ['core', 'page', 'site', 'node', 'content', 'items', 'infosystems'], true)) {
                    return [];
                }

                return [$code => $linked];
            },
        );
    }
}
