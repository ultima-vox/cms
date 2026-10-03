<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Sites;

use Core\Config;
use Core\Delivery\DeliveryInvalidatingHandler;
use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\Http\Request;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\SiteRepository;
use Core\Repository\UserRepository;
use Core\Security\AuthService;
use Core\Site\AdminSiteSelector;
use Core\Site\SiteResolver;
use Core\View\AdminPhpRendererFactory;

final class SitesModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $db = $core->runtime()->database();
        $sites = new SiteRepository($db);
        $selector = new AdminSiteSelector($sites);
        $auth = new AuthService(
            new UserRepository($db),
            new LoginAttemptRepository($db),
        );
        $view = (new AdminPhpRendererFactory(
            $core->runtime()->rootPath(),
            $auth,
            $core->admin(),
            $core->sites(),
        ))->create();
        $controller = new SitesController(
            $auth,
            new SitesManagementRepository($db),
            $selector,
            new SiteResolver($sites, Config::string('APP_URL', 'http://localhost')),
            $view,
        );
        $invalidate = new DeliveryInvalidatingHandler($core->delivery());

        $core->permissions()->define(
            'sites.manage',
            'Manage sites and domains',
            ['superadmin', 'admin'],
        );
        $core->admin()->navigation(
            'sites',
            'Сайты',
            '/admin/sites',
            'sites.manage',
            5,
            'globe',
        );

        $siteTags = static function (Request $request, array $variables): array {
            $id = $variables['id'] ?? '';
            return ctype_digit($id) ? ['site:' . (int) $id] : [];
        };

        $routes = $core->routes();
        $routes->post('/admin/sites/select', 'sites.select', [$controller, 'select']);
        $routes->get('/admin/sites', 'sites.index', [$controller, 'index']);
        $routes->get('/admin/sites/create', 'sites.create', [$controller, 'createForm']);
        $routes->post('/admin/sites', 'sites.store', [$controller, 'store']);
        $routes->get('/admin/sites/{id:\\d+}/edit', 'sites.edit', [$controller, 'editForm']);
        $routes->post('/admin/sites/{id:\\d+}', 'sites.update', $invalidate->wrap([$controller, 'update'], $siteTags));
        $routes->post('/admin/sites/{id:\\d+}/domains', 'sites.domain.store', $invalidate->wrap([$controller, 'addDomain'], $siteTags));
        $routes->post('/admin/sites/{id:\\d+}/domains/{domainId:\\d+}/primary', 'sites.domain.primary', $invalidate->wrap([$controller, 'makePrimary'], $siteTags));
        $routes->post('/admin/sites/{id:\\d+}/domains/{domainId:\\d+}/delete', 'sites.domain.delete', $invalidate->wrap([$controller, 'deleteDomain'], $siteTags));
    }
}
