<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Content\HtmlSanitizer;
use Core\Content\HtmlSanitizingHandler;
use Core\Controller\InfosystemController;
use Core\Controller\InfosystemItemListController;
use Core\Delivery\DeliveryInvalidatingHandler;
use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\Http\Request;
use Core\Infosystem\FieldSchema;
use Core\Repository\AuditLogRepository;
use Core\Repository\InfosystemItemSearchRepository;
use Core\Repository\InfosystemManagementRepository;
use Core\Repository\InfosystemRepository;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\UserRepository;
use Core\Security\AuthService;
use Core\Security\PermissionGate;
use Core\View\Render\RenderNodeInterface;
use Core\View\Render\TemplateFacadeContext;
use Core\View\TwigRenderer;
use RuntimeException;

final class InfosystemModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $db = $core->runtime()->database();
        $repository = new InfosystemRepository($db);
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

        $this->registerAdminRoutes($core);

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

    private function registerAdminRoutes(Core $core): void
    {
        $db = $core->runtime()->database();
        $siteId = $core->sites()->adminId();
        $twig = new TwigRenderer(
            $core->runtime()->rootPath(),
            [dirname(__DIR__) . '/templates'],
        );
        $auth = new AuthService(
            new UserRepository($db),
            new LoginAttemptRepository($db),
        );
        $management = new InfosystemManagementRepository($db, $siteId);
        $controller = new InfosystemController(
            $auth,
            $management,
            new FieldSchema(),
            new AuditLogRepository($db),
            $twig,
        );
        $listController = new InfosystemItemListController(
            $auth,
            $management,
            new InfosystemItemSearchRepository($db),
            $twig,
        );
        $gate = new PermissionGate($auth);
        $html = new HtmlSanitizingHandler(new HtmlSanitizer());
        $invalidate = new DeliveryInvalidatingHandler($core->delivery());
        $routes = $core->routes();

        $siteTags = static fn (Request $request, array $variables): array => ['site:' . $siteId];
        $systemTags = static function (Request $request, array $variables) use ($siteId): array {
            $id = $variables['id'] ?? '';
            return ctype_digit($id)
                ? ['site:' . $siteId . ':infosystem:' . (int) $id]
                : ['site:' . $siteId];
        };

        $routes->get('/admin/infosystems', 'infosystem.index', [$controller, 'index']);
        $routes->get('/admin/infosystems/create', 'infosystem.create', [$controller, 'createForm']);
        $routes->post(
            '/admin/infosystems',
            'infosystem.store',
            $gate->require(
                'infosystems.manage',
                $invalidate->wrap($html->wrap([$controller, 'store'], ['description' => 'rich']), $siteTags),
            ),
        );
        $routes->get('/admin/infosystems/{id:\\d+}', 'infosystem.manage', [$listController, 'overview']);
        $routes->get('/admin/infosystems/{id:\\d+}/edit', 'infosystem.edit', [$controller, 'editForm']);
        $routes->post(
            '/admin/infosystems/{id:\\d+}',
            'infosystem.update',
            $gate->require(
                'infosystems.manage',
                $invalidate->wrap($html->wrap([$controller, 'update'], ['description' => 'rich']), $systemTags),
            ),
        );
        $routes->post(
            '/admin/infosystems/{id:\\d+}/delete',
            'infosystem.delete',
            $gate->require('infosystems.manage', $invalidate->wrap([$controller, 'delete'], $systemTags)),
        );

        $routes->get('/admin/infosystems/{id:\\d+}/groups/create', 'infosystem.group.create', [$controller, 'createGroupForm']);
        $routes->post(
            '/admin/infosystems/{id:\\d+}/groups',
            'infosystem.group.store',
            $gate->require(
                'infosystems.manage',
                $invalidate->wrap($html->wrap([$controller, 'storeGroup'], ['description' => 'rich']), $systemTags),
            ),
        );
        $routes->get('/admin/infosystems/{id:\\d+}/groups/{groupId:\\d+}/edit', 'infosystem.group.edit', [$controller, 'editGroupForm']);
        $routes->post(
            '/admin/infosystems/{id:\\d+}/groups/{groupId:\\d+}',
            'infosystem.group.update',
            $gate->require(
                'infosystems.manage',
                $invalidate->wrap($html->wrap([$controller, 'updateGroup'], ['description' => 'rich']), $systemTags),
            ),
        );
        $routes->post(
            '/admin/infosystems/{id:\\d+}/groups/{groupId:\\d+}/delete',
            'infosystem.group.delete',
            $gate->require('infosystems.manage', $invalidate->wrap([$controller, 'deleteGroup'], $systemTags)),
        );

        $routes->get('/admin/infosystems/{id:\\d+}/items', 'infosystem.item.index', [$listController, 'index']);
        $routes->get('/admin/infosystems/{id:\\d+}/items/create', 'infosystem.item.create', [$controller, 'createItemForm']);
        $routes->post(
            '/admin/infosystems/{id:\\d+}/items',
            'infosystem.item.store',
            $gate->require(
                'infosystems.manage',
                $invalidate->wrap(
                    $html->wrap([$controller, 'storeItem'], ['description' => 'rich', 'content' => 'rich']),
                    $systemTags,
                ),
            ),
        );
        $routes->get('/admin/infosystems/{id:\\d+}/items/{itemId:\\d+}/edit', 'infosystem.item.edit', [$controller, 'editItemForm']);
        $routes->post(
            '/admin/infosystems/{id:\\d+}/items/{itemId:\\d+}',
            'infosystem.item.update',
            $gate->require(
                'infosystems.manage',
                $invalidate->wrap(
                    $html->wrap([$controller, 'updateItem'], ['description' => 'rich', 'content' => 'rich']),
                    $systemTags,
                ),
            ),
        );
        $routes->post(
            '/admin/infosystems/{id:\\d+}/items/{itemId:\\d+}/delete',
            'infosystem.item.delete',
            $gate->require('infosystems.manage', $invalidate->wrap([$controller, 'deleteItem'], $systemTags)),
        );
    }
}
