<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Extension\ModuleMigrationRunner;
use Core\Extension\ModuleStateRepository;
use Core\Site\SiteContext;
use Core\View\Render\RenderEngine;
use Core\View\Render\TemplateFacadeContext;
use Core\View\Render\ViewTemplateRenderer;
use UltimaVox\Modules\Menu\MenusFacade;

$rootPath = dirname(__DIR__);
require_once $rootPath . '/vendor/autoload.php';

$db = Database::connection();
$migrations = new ModuleMigrationRunner($db, $rootPath);
$migrations->migrate('menu');
$migrations->migrate('infosystem');

$loader = new ModuleLoader($rootPath);
$state = new ModuleStateRepository($db);
$state->sync($loader->discover());
$state->setEnabled('menu-infosystem', true);
$bridgeEnabled = true;

$siteStatement = $db->query(
    "INSERT INTO sites (code, name) VALUES ('menu-infosystem-smoke', 'Menu Infosystem Smoke') RETURNING id"
);
$siteId = (int) $siteStatement->fetchColumn();
if ($siteId < 1) {
    throw new RuntimeException('Menu Infosystem smoke site was not created.');
}

try {
    $infosystemStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO infosystems (site_id, name, code, field_schema, is_active)
        VALUES (:site_id, 'Catalog data', 'catalog-data', '[]'::jsonb, TRUE)
        RETURNING id
        SQL
    );
    $infosystemStatement->execute(['site_id' => $siteId]);
    $infosystemId = (int) $infosystemStatement->fetchColumn();

    $groupStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO infosystem_groups (
            infosystem_id, parent_id, name, slug, path, sorting, is_active
        )
        VALUES (
            :infosystem_id, :parent_id, :name, :slug, :path, :sorting, CAST(:is_active AS BOOLEAN)
        )
        RETURNING id
        SQL
    );
    $addGroup = static function (
        ?int $parentId,
        string $name,
        string $slug,
        string $path,
        int $sorting,
        bool $isActive = true,
    ) use ($groupStatement, $infosystemId): int {
        $groupStatement->execute([
            'infosystem_id' => $infosystemId,
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'path' => $path,
            'sorting' => $sorting,
            'is_active' => $isActive ? 'true' : 'false',
        ]);

        return (int) $groupStatement->fetchColumn();
    };

    $hardwareId = $addGroup(null, 'Hardware', 'hardware', '/hardware', 10);
    $phonesId = $addGroup($hardwareId, 'Phones', 'phones', '/hardware/phones', 10);
    $hiddenGroupId = $addGroup(null, 'Hidden group', 'hidden', '/hidden', 20, false);

    $itemStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO infosystem_items (
            infosystem_id, group_id, name, slug, path, status, is_active, sorting,
            properties, content
        )
        VALUES (
            :infosystem_id, :group_id, :name, :slug, :path, :status,
            CAST(:is_active AS BOOLEAN), :sorting, '{}'::jsonb, ''
        )
        RETURNING id
        SQL
    );
    $addItem = static function (
        ?int $groupId,
        string $name,
        string $slug,
        string $path,
        int $sorting,
        string $status = 'published',
        bool $isActive = true,
    ) use ($itemStatement, $infosystemId): int {
        $itemStatement->execute([
            'infosystem_id' => $infosystemId,
            'group_id' => $groupId,
            'name' => $name,
            'slug' => $slug,
            'path' => $path,
            'status' => $status,
            'is_active' => $isActive ? 'true' : 'false',
            'sorting' => $sorting,
        ]);

        return (int) $itemStatement->fetchColumn();
    };

    $featuredId = $addItem(null, 'Featured', 'featured', '/featured', 5);
    $androidId = $addItem(
        $phonesId,
        'Android',
        'android',
        '/hardware/phones/android',
        20,
    );
    $addItem(
        $phonesId,
        'Draft phone',
        'draft-phone',
        '/hardware/phones/draft-phone',
        30,
        'draft',
    );
    $addItem(
        $hiddenGroupId,
        'Hidden item',
        'hidden-item',
        '/hidden/hidden-item',
        10,
    );

    $menuStatement = $db->prepare(
        "INSERT INTO menus (site_id, code, name) VALUES (:site_id, 'catalog', 'Catalog menu') RETURNING id"
    );
    $menuStatement->execute(['site_id' => $siteId]);
    $menuId = (int) $menuStatement->fetchColumn();

    $sourceStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO menu_items (menu_id, kind, source_code, source_config)
        VALUES (
            :menu_id,
            'source',
            'infosystem.navigation',
            CAST(:config AS jsonb)
        )
        RETURNING id
        SQL
    );
    $sourceStatement->execute([
        'menu_id' => $menuId,
        'config' => json_encode([
            'infosystem' => 'catalog-data',
            'base_path' => '/services',
            'groups' => true,
            'items' => true,
            'depth' => 2,
            'limit' => 50,
        ], JSON_THROW_ON_ERROR),
    ]);
    $sourceItemId = (int) $sourceStatement->fetchColumn();

    $core = new ExtensionCore(new RuntimeApi($db, $rootPath));
    $loaded = $loader->load($core);
    if (!in_array('menu-infosystem', $loaded, true)) {
        throw new RuntimeException('Menu Infosystem bridge module was not loaded after explicit enable.');
    }
    $core->freeze();

    $engine = new RenderEngine();
    $templateContext = new TemplateFacadeContext(
        $engine,
        [
            'site' => new SiteContext(
                $siteId,
                'menu-infosystem-smoke',
                'Menu Infosystem Smoke',
                'menu-infosystem.test',
            ),
            'node' => [
                'id' => 500,
                'site_id' => $siteId,
                'path' => '/services/hardware/phones/android/',
            ],
        ],
        new ViewTemplateRenderer($rootPath, $core->templates()),
    );
    $facades = $core->templates()->instantiateFacades($templateContext);
    $menus = $facades['menus'] ?? null;
    if (!$menus instanceof MenusFacade) {
        throw new RuntimeException('Menus facade was not exposed for Infosystem bridge smoke.');
    }

    $html = $menus->get('catalog')->show();
    if (!str_contains($html, '>Featured</a>')
        || !str_contains($html, '>Hardware</a>')
        || !str_contains($html, '>Phones</a>')
        || !str_contains($html, '>Android</a>')
        || !str_contains($html, 'href="/services/hardware/phones/android/"')
        || !str_contains($html, 'aria-current="page"')
        || str_contains($html, '>Draft phone</a>')
        || str_contains($html, '>Hidden group</a>')
        || str_contains($html, '>Hidden item</a>')) {
        throw new RuntimeException('Infosystem navigation bridge rendered an unexpected menu tree.');
    }

    $dependencies = $engine->context()->dependencies();
    foreach ([
        'menu:' . $menuId,
        'menu_item:' . $sourceItemId,
        'infosystem:' . $infosystemId,
        'site:' . $siteId . ':infosystem:' . $infosystemId,
        'infosystem_group:' . $hardwareId,
        'infosystem_group:' . $phonesId,
        'infosystem_item:' . $featuredId,
        'infosystem_item:' . $androidId,
    ] as $dependency) {
        if (!in_array($dependency, $dependencies, true)) {
            throw new RuntimeException('Infosystem bridge dependency is missing: ' . $dependency);
        }
    }
} finally {
    if ($bridgeEnabled) {
        $state->setEnabled('menu-infosystem', false);
    }

    $deleteInfosystem = $db->prepare('DELETE FROM infosystems WHERE id = :id');
    $deleteInfosystem->execute(['id' => $infosystemId ?? 0]);

    $deleteSite = $db->prepare('DELETE FROM sites WHERE id = :site_id');
    $deleteSite->execute(['site_id' => $siteId]);
}

fwrite(STDOUT, "MENU INFOSYSTEM BRIDGE OK\n");
