<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Site\SiteContext;
use Core\View\Render\RenderEngine;
use Core\View\Render\TemplateFacadeContext;
use Core\View\Render\ViewTemplateRenderer;
use UltimaVox\Modules\Infosystem\Event\InfosystemItemsRendered;

$rootPath = dirname(__DIR__);
require $rootPath . '/vendor/autoload.php';

$db = Database::connection();
$core = new ExtensionCore(new RuntimeApi($db, $rootPath));
$loaded = (new ModuleLoader($rootPath))->load($core);

if (!in_array('infosystem', $loaded, true)) {
    throw new RuntimeException('Infosystem module was not discovered.');
}
if (!$core->pages()->has('infosystem.list')) {
    throw new RuntimeException('Infosystem page executor was not registered.');
}

$definition = $core->pages()->definition('infosystem.list');
$properties = $definition->configurationSchema['properties'] ?? null;
if (!is_array($properties) || array_key_exists('include_content', $properties)) {
    throw new RuntimeException('Infosystem page schema still exposes legacy include_content.');
}

try {
    $core->pages()->validateConfiguration('infosystem.list', ['include_content' => true]);
    throw new RuntimeException('Infosystem page validator still accepts legacy include_content.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Infosystem page validator still accepts legacy include_content.') {
        throw $exception;
    }
}

$renderedEvent = null;
$core->events()->listen(
    InfosystemItemsRendered::class,
    static function (InfosystemItemsRendered $event) use (&$renderedEvent): void {
        $renderedEvent = $event;
    },
);
$core->freeze();

$db->exec(
    <<<'SQL'
    UPDATE nodes n
    SET page_type = 'infosystem.list',
        updated_at = CURRENT_TIMESTAMP
    FROM node_module_bindings b
    WHERE b.node_id = n.id
      AND b.module_code = 'infosystem'
      AND b.binding_code = 'primary'
      AND n.page_type = 'core.content'
    SQL
);

$infosystemId = (int) $db->query("SELECT id FROM infosystems WHERE site_id = 1 AND code = 'catalog'")->fetchColumn();
$nodeId = (int) $db->query("SELECT id FROM nodes WHERE site_id = 1 AND path = '/'")->fetchColumn();
$engine = new RenderEngine();
$context = new TemplateFacadeContext(
    $engine,
    [
        'site' => new SiteContext(1, 'default', 'Default site', '127.0.0.1'),
        'node' => ['id' => $nodeId, 'site_id' => 1],
    ],
    new ViewTemplateRenderer($rootPath, $core->templates()),
);
$facades = $core->templates()->instantiateFacades($context);

if (!isset($facades['infosystems'], $facades['catalog'])) {
    throw new RuntimeException('Infosystem fixed facade or linked short alias was not exposed.');
}

$generic = $facades['infosystems']->get('catalog');
if ($generic->id() !== $infosystemId || $facades['catalog']->id() !== $infosystemId) {
    throw new RuntimeException('Infosystem facade resolved an unexpected entity.');
}

$html = $facades['catalog']
    ->items()
    ->where('kind', 'bio')
    ->limit(5)
    ->show();

if (!str_contains($html, 'Tank 5') || !str_contains($html, 'data-infosystem="catalog"')) {
    throw new RuntimeException('Infosystem module view did not render filtered content.');
}

if (!$renderedEvent instanceof InfosystemItemsRendered
    || $renderedEvent->infosystemId !== $infosystemId
    || $renderedEvent->itemCount !== 1) {
    throw new RuntimeException('Infosystem rendered event was not dispatched correctly.');
}

$dependencies = $engine->context()->dependencies();
if (!in_array('site:1:infosystem:' . $infosystemId, $dependencies, true)) {
    throw new RuntimeException('Site-aware infosystem dependency tag was not registered.');
}

$adminCodes = array_map(static fn ($item): string => $item->code, $core->admin()->items());
if (!in_array('infosystems', $adminCodes, true)) {
    throw new RuntimeException('Infosystem admin navigation metadata is missing.');
}

$permissionCodes = array_map(static fn ($definition): string => $definition->code, $core->permissions()->definitions());
if (!in_array('infosystems.manage', $permissionCodes, true)) {
    throw new RuntimeException('Infosystem permission metadata is missing.');
}

// A dedicated item page stays within the linked infosystem and site.
if (!$core->pages()->has('infosystem.item')) {
    throw new RuntimeException('Infosystem item page type was not registered.');
}

$itemDefinition = $core->pages()->definition('infosystem.item');
if ($itemDefinition->validateConfiguration(['item_path' => '/service']) !== ['item_path' => '/service']) {
    throw new RuntimeException('Infosystem item path configuration was changed.');
}

foreach (['', 'service', '/service/../x', '/service?token=x'] as $invalidPath) {
    try {
        $itemDefinition->validateConfiguration(['item_path' => $invalidPath]);
        throw new RuntimeException('Invalid item path was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Invalid item path was accepted.') {
            throw $exception;
        }
    }
}

$itemRepository = new \UltimaVox\Modules\Infosystem\Repository\InfosystemRepository($db);
$visible = $itemRepository->findPublishedItems(1, $infosystemId, 50);
$first = $visible[0] ?? null;
if (!is_array($first) || !is_string($first['path'] ?? null)) {
    throw new RuntimeException('Expected published catalog fixture item.');
}

$publishedPath = $first['path'];
$actual = $itemRepository->findPublishedItemByPath(1, $infosystemId, $publishedPath);
if (!is_array($actual) || (int) $actual['id'] !== (int) $first['id']) {
    throw new RuntimeException('Published item lookup did not resolve its item path.');
}
if ($itemRepository->findPublishedItemByPath(2, $infosystemId, $publishedPath) !== null
    || $itemRepository->findPublishedItemByPath(1, $infosystemId, '/missing-item') !== null) {
    throw new RuntimeException('Published item lookup leaked a site or unknown record.');
}

$itemPageContext = static fn (int $siteId, string $path): \Core\Page\PageExecutionContext
    => new \Core\Page\PageExecutionContext(
        new \Core\Http\Request('GET', '/', '/', [], [], [], []),
        new SiteContext($siteId, 'item-test', 'Item test', 'localhost'),
        ['id' => $nodeId, 'site_id' => $siteId, 'path' => '/', 'title' => 'Item test'],
        ['item_path' => $path],
        new \Core\View\Render\RenderContext(),
    );

$itemContext = $itemPageContext(1, $publishedPath);
$itemHtml = $itemDefinition->executor->execute($itemContext)->value();
if (!str_contains($itemHtml, (string) $first['name'])) {
    throw new RuntimeException('Native infosystem.item page did not render the published item.');
}
if (!in_array('site:1:infosystem_item:' . (int) $first['id'], $itemContext->renderContext->dependencies(), true)
    || !in_array('site:1:infosystem:' . $infosystemId, $itemContext->renderContext->dependencies(), true)) {
    throw new RuntimeException('Infosystem item page cache tags are incomplete.');
}

try {
    $itemDefinition->executor->execute($itemPageContext(2, $publishedPath));
    throw new RuntimeException('Foreign-site infosystem item was exposed.');
} catch (\Core\Page\PageNotFoundException) {
    // An item exists in one site only.
}

$db->beginTransaction();
try {
    $hide = $db->prepare("UPDATE infosystem_items SET status = 'draft' WHERE id = :id");
    $hide->execute(['id' => $first['id']]);
    if ($itemRepository->findPublishedItemByPath(1, $infosystemId, $publishedPath) !== null) {
        throw new RuntimeException('Draft item was exposed.');
    }
    try {
        $itemDefinition->executor->execute($itemPageContext(1, $publishedPath));
        throw new RuntimeException('Draft item page rendered without a 404.');
    } catch (\Core\Page\PageNotFoundException) {
        // Valid 404 gate.
    }

    $future = $db->prepare(
        "UPDATE infosystem_items SET status = 'published', publish_at = '2099-12-31 00:00:00' WHERE id = :id"
    );
    $future->execute(['id' => $first['id']]);
    if ($itemRepository->findPublishedItemByPath(1, $infosystemId, $publishedPath) !== null) {
        throw new RuntimeException('Future item was exposed.');
    }
} finally {
    $db->rollBack();
}

require __DIR__ . '/module_template_scope_smoke.php';

fwrite(STDOUT, "INFOSYSTEM MODULE OK\n");
