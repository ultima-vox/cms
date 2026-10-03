<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Extension\ModuleMigrationRunner;
use Core\Site\SiteContext;
use Core\View\Render\RenderEngine;
use Core\View\Render\TemplateFacadeContext;
use Core\View\Render\ViewTemplateRenderer;
use UltimaVox\Modules\Menu\MenusFacade;

$rootPath = dirname(__DIR__);
require_once $rootPath . '/vendor/autoload.php';
require_once $rootPath . '/modules/menu/autoload.php';

$db = Database::connection();
$migrations = new ModuleMigrationRunner($db, $rootPath);
$applied = $migrations->migrate('menu');
if ($applied !== ['001_menu.sql'] && $applied !== []) {
    throw new RuntimeException('Unexpected Menu module migration result.');
}

$siteStatement = $db->query(
    "INSERT INTO sites (code, name) VALUES ('menu-smoke', 'Menu Smoke') RETURNING id"
);
$siteId = (int) $siteStatement->fetchColumn();
if ($siteId < 1) {
    throw new RuntimeException('Menu smoke-test site was not created.');
}

try {
    $menuStatement = $db->prepare(
        "INSERT INTO menus (site_id, code, name) VALUES (:site_id, 'main', 'Main navigation') RETURNING id"
    );
    $menuStatement->execute(['site_id' => $siteId]);
    $menuId = (int) $menuStatement->fetchColumn();

    $insertItem = $db->prepare(
        <<<'SQL'
        INSERT INTO menu_items (menu_id, parent_id, kind, label, url, sorting, is_active)
        VALUES (:menu_id, :parent_id, 'link', :label, :url, :sorting, CAST(:is_active AS BOOLEAN))
        RETURNING id
        SQL
    );
    $addItem = static function (
        ?int $parentId,
        string $label,
        string $url,
        int $sorting,
        bool $isActive = true,
    ) use ($insertItem, $menuId): int {
        $insertItem->execute([
            'menu_id' => $menuId,
            'parent_id' => $parentId,
            'label' => $label,
            'url' => $url,
            'sorting' => $sorting,
            'is_active' => $isActive ? 'true' : 'false',
        ]);

        return (int) $insertItem->fetchColumn();
    };

    $homeId = $addItem(null, 'Home', '/', 0);
    $catalogId = $addItem(null, 'Catalog', '/catalog/', 10);
    $phonesId = $addItem($catalogId, 'Phones', '/catalog/phones/', 0);
    $hiddenId = $addItem($catalogId, 'Hidden', '/catalog/hidden/', 10, false);
    $hiddenChildId = $addItem($hiddenId, 'Hidden child', '/catalog/hidden/child/', 0);

    $core = new ExtensionCore(new RuntimeApi($db, $rootPath));
    (new ModuleLoader($rootPath))->load($core);
    $core->freeze();

    $engine = new RenderEngine();
    $templateContext = new TemplateFacadeContext(
        $engine,
        [
            'site' => new SiteContext($siteId, 'menu-smoke', 'Menu Smoke', 'menu-smoke.test'),
            'node' => ['id' => 100, 'site_id' => $siteId, 'path' => '/catalog/phones/'],
        ],
        new ViewTemplateRenderer($rootPath, $core->templates()),
    );
    $facades = $core->templates()->instantiateFacades($templateContext);
    $menus = $facades['menus'] ?? null;
    if (!$menus instanceof MenusFacade) {
        throw new RuntimeException('Menus template facade was not exposed.');
    }

    $html = $menus->get('MAIN')->view('default')->show();
    if (!str_contains($html, 'data-menu="main"')
        || !str_contains($html, '>Catalog</a>')
        || !str_contains($html, '>Phones</a>')
        || !str_contains($html, 'aria-current="page"')
        || str_contains($html, '>Hidden</a>')
        || str_contains($html, '>Hidden child</a>')) {
        throw new RuntimeException('Menu facade rendered an unexpected tree.');
    }

    $dependencies = $engine->context()->dependencies();
    foreach ([
        'menu:' . $menuId,
        'site:' . $siteId . ':menu:' . $menuId,
        'site:' . $siteId . ':menu:main',
        'menu_item:' . $homeId,
        'menu_item:' . $catalogId,
        'menu_item:' . $phonesId,
        'menu_item:' . $hiddenId,
        'menu_item:' . $hiddenChildId,
    ] as $dependency) {
        if (!in_array($dependency, $dependencies, true)) {
            throw new RuntimeException('Menu dependency tag is missing: ' . $dependency);
        }
    }

    $secondSiteStatement = $db->query(
        "INSERT INTO sites (code, name) VALUES ('menu-smoke-second', 'Menu Smoke Second') RETURNING id"
    );
    $secondSiteId = (int) $secondSiteStatement->fetchColumn();
    $secondMenu = $db->prepare(
        "INSERT INTO menus (site_id, code, name) VALUES (:site_id, 'main', 'Second navigation') RETURNING id"
    );
    $secondMenu->execute(['site_id' => $secondSiteId]);
    $secondMenuId = (int) $secondMenu->fetchColumn();
    $secondItem = $db->prepare(
        <<<'SQL'
        INSERT INTO menu_items (menu_id, kind, label, url)
        VALUES (:menu_id, 'link', 'Second site only', '/second/')
        SQL
    );
    $secondItem->execute(['menu_id' => $secondMenuId]);

    $secondEngine = new RenderEngine();
    $secondContext = new TemplateFacadeContext(
        $secondEngine,
        [
            'site' => new SiteContext($secondSiteId, 'menu-smoke-second', 'Menu Smoke Second', 'second.test'),
            'node' => ['id' => 200, 'site_id' => $secondSiteId, 'path' => '/second/'],
        ],
        new ViewTemplateRenderer($rootPath, $core->templates()),
    );
    $secondFacades = $core->templates()->instantiateFacades($secondContext);
    $secondMenus = $secondFacades['menus'] ?? null;
    if (!$secondMenus instanceof MenusFacade) {
        throw new RuntimeException('Second-site Menus facade was not exposed.');
    }
    $secondHtml = $secondMenus->get('main')->show();
    if (!str_contains($secondHtml, 'Second site only') || str_contains($secondHtml, 'Phones')) {
        throw new RuntimeException('Menu facade did not preserve site isolation.');
    }
} finally {
    $deleteSites = $db->prepare(
        "DELETE FROM sites WHERE code IN ('menu-smoke', 'menu-smoke-second')"
    );
    $deleteSites->execute();
}

fwrite(STDOUT, "MENU FRONTEND OK\n");
