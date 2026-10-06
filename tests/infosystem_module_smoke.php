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

require __DIR__ . '/module_template_scope_smoke.php';

fwrite(STDOUT, "INFOSYSTEM MODULE OK\n");
