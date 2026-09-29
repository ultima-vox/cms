<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Sites;

use Core\Config;
use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\SiteRepository;
use Core\Repository\UserRepository;
use Core\Security\AuthService;
use Core\Site\AdminSiteSelector;
use Core\Site\SiteResolver;
use Core\View\TwigRenderer;

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
        $controller = new SitesController(
            $auth,
            new SitesManagementRepository($db),
            $selector,
            new SiteResolver($sites, Config::string('APP_URL', 'http://localhost')),
            new TwigRenderer($core->runtime()->rootPath()),
        );

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
        );

        $routes = $core->routes();
        $routes->post('/admin/sites/select', 'sites.select', [$controller, 'select']);
        $routes->get('/admin/sites', 'sites.index', [$controller, 'index']);
        $routes->get('/admin/sites/create', 'sites.create', [$controller, 'createForm']);
        $routes->post('/admin/sites', 'sites.store', [$controller, 'store']);
        $routes->get('/admin/sites/{id:\\d+}/edit', 'sites.edit', [$controller, 'editForm']);
        $routes->post('/admin/sites/{id:\\d+}', 'sites.update', [$controller, 'update']);
        $routes->post('/admin/sites/{id:\\d+}/domains', 'sites.domain.store', [$controller, 'addDomain']);
        $routes->post(
            '/admin/sites/{id:\\d+}/domains/{domainId:\\d+}/primary',
            'sites.domain.primary',
            [$controller, 'makePrimary'],
        );
        $routes->post(
            '/admin/sites/{id:\\d+}/domains/{domainId:\\d+}/delete',
            'sites.domain.delete',
            [$controller, 'deleteDomain'],
        );
    }
}
