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
$migrations->migrate('menu');

$siteStatement = $db->query(
    "INSERT INTO sites (code, name) VALUES ('menu-structure-smoke', 'Menu Structure Smoke') RETURNING id"
);
$siteId = (int) $siteStatement->fetchColumn();
if ($siteId < 1) {
    throw new RuntimeException('Menu Structure smoke site was not created.');
}

$insertNode = $db->prepare(
    <<<'SQL'
    INSERT INTO nodes (
        site_id, parent_id, name, slug, path, title, content, status,
        is_active, sorting, page_type, page_config
    )
    VALUES (
        :site_id, :parent_id, :name, :slug, :path, :title, '', :status,
        CAST(:is_active AS BOOLEAN), :sorting, 'fixture.page', '{}'::jsonb
    )
    RETURNING id
    SQL
);
$addNode = static function (
    ?int $parentId,
    string $name,
    string $slug,
    string $path,
    int $sorting,
    string $status = 'published',
    bool $isActive = true,
) use ($insertNode, $siteId): int {
    $insertNode->execute([
        'site_id' => $siteId,
        'parent_id' => $parentId,
        'name' => $name,
        'slug' => $slug,
        'path' => $path,
        'title' => $name,
        'status' => $status,
        'is_active' => $isActive ? 'true' : 'false',
        'sorting' => $sorting,
    ]);

    return (int) $insertNode->fetchColumn();
};

try {
    $rootId = $addNode(null, 'Root', '', '/', 0);
    $catalogId = $addNode($rootId, 'Catalog', 'catalog', '/catalog', 0);
    $phonesId = $addNode($catalogId, 'Phones', 'phones', '/catalog/phones', 0);
    $androidId = $addNode($phonesId, 'Android', 'android', '/catalog/phones/android', 0);
    $addNode($catalogId, 'Draft', 'draft', '/catalog/draft', 10, 'draft');

    $menuStatement = $db->prepare(
        "INSERT INTO menus (site_id, code, name) VALUES (:site_id, 'catalog', 'Catalog navigation') RETURNING id"
    );
    $menuStatement->execute(['site_id' => $siteId]);
    $menuId = (int) $menuStatement->fetchColumn();

    $sourceStatement = $db->prepare(
        <<<'SQL'
        INSERT INTO menu_items (menu_id, kind, source_code, source_config)
        VALUES (
            :menu_id,
            'source',
            'structure.children',
            '{"parent_path":"/catalog","depth":2}'::jsonb
        )
        RETURNING id
        SQL
    );
    $sourceStatement->execute(['menu_id' => $menuId]);
    $sourceItemId = (int) $sourceStatement->fetchColumn();

    $core = new ExtensionCore(new RuntimeApi($db, $rootPath));
    (new ModuleLoader($rootPath))->load($core);
    $core->freeze();

    $engine = new RenderEngine();
    $templateContext = new TemplateFacadeContext(
        $engine,
        [
            'site' => new SiteContext(
                $siteId,
                'menu-structure-smoke',
                'Menu Structure Smoke',
                'menu-structure.test',
            ),
            'node' => [
                'id' => $androidId,
                'site_id' => $siteId,
                'path' => '/catalog/phones/android',
            ],
        ],
        new ViewTemplateRenderer($rootPath, $core->templates()),
    );
    $facades = $core->templates()->instantiateFacades($templateContext);
    $menus = $facades['menus'] ?? null;
    if (!$menus instanceof MenusFacade) {
        throw new RuntimeException('Menus facade was not available for Structure source smoke.');
    }

    $html = $menus->get('catalog')->show();
    if (!str_contains($html, '>Phones</a>')
        || !str_contains($html, '>Android</a>')
        || !str_contains($html, 'href="/catalog/phones/android"')
        || !str_contains($html, 'aria-current="page"')
        || str_contains($html, '>Catalog</a>')
        || str_contains($html, '>Draft</a>')) {
        throw new RuntimeException('Structure navigation source rendered an unexpected tree.');
    }

    $dependencies = $engine->context()->dependencies();
    foreach ([
        'menu:' . $menuId,
        'menu_item:' . $sourceItemId,
        'site:' . $siteId . ':structure',
        'node:' . $catalogId,
        'site:' . $siteId . ':node:' . $catalogId,
        'node:' . $phonesId,
        'site:' . $siteId . ':node:' . $phonesId,
        'node:' . $androidId,
        'site:' . $siteId . ':node:' . $androidId,
    ] as $dependency) {
        if (!in_array($dependency, $dependencies, true)) {
            throw new RuntimeException('Structure navigation dependency is missing: ' . $dependency);
        }
    }
} finally {
    $deleteNodes = $db->prepare('DELETE FROM nodes WHERE site_id = :site_id');
    $deleteNodes->execute(['site_id' => $siteId]);

    $deleteSite = $db->prepare('DELETE FROM sites WHERE id = :site_id');
    $deleteSite->execute(['site_id' => $siteId]);
}

fwrite(STDOUT, "MENU STRUCTURE SOURCE OK\n");
