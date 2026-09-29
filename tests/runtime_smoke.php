<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\View\PhpRenderer;
use Core\View\Render\RenderContext;
use Core\View\Render\RenderEngine;
use Core\View\Render\RenderNodeInterface;
use Core\View\Render\RenderSource;
use Core\View\Render\TemplateFacadeContext;
use Core\View\Render\ViewTemplateRenderer;
use Core\View\SafeHtml;

require dirname(__DIR__) . '/vendor/autoload.php';

final class SmokeEvent
{
    public bool $handled = false;
}

$projectRoot = dirname(__DIR__);
$core = new ExtensionCore(new RuntimeApi(Database::connection(), $projectRoot));
$core->routes()->get('/smoke', 'smoke.route', static fn (): null => null);
$core->extensions()->register('smoke.point', 'smoke.extension', new stdClass());
$core->admin()->navigation('smoke', 'Smoke', '/admin/smoke', null, 500);
$core->permissions()->define('smoke.manage', 'Manage smoke', ['admin']);
$core->events()->listen(SmokeEvent::class, static function (SmokeEvent $event): void {
    $event->handled = true;
});
$core->content()->source(
    'smoke.source',
    static fn (TemplateFacadeContext $context, array $options): RenderNodeInterface => new class($context->renderEngine()) extends RenderSource {
        public function render(RenderContext $context): string
        {
            $context->dependency('smoke.source:1');

            return 'content-source';
        }
    },
);
$core->templates()->facade('smokeFacade', static fn (TemplateFacadeContext $context): object => new stdClass());
$core->templates()->facadeProvider(
    static fn (TemplateFacadeContext $context, array $facades): array => ['smokeAlias' => $facades['smokeFacade']],
);
$core->freeze();

try {
    $core->routes()->get('/late', 'late.route', static fn (): null => null);
    throw new RuntimeException('Frozen route registry accepted a late registration.');
} catch (LogicException) {
    // Expected.
}

try {
    $core->content()->source('late.source', static fn (): null => null);
    throw new RuntimeException('Frozen content registry accepted a late registration.');
} catch (LogicException) {
    // Expected.
}

$event = new SmokeEvent();
$core->events()->dispatch($event);
if (!$event->handled) {
    throw new RuntimeException('Event listener was not dispatched after registry freeze.');
}

if (($core->admin()->items()[0]->code ?? null) !== 'smoke') {
    throw new RuntimeException('Admin registry did not expose the registered navigation item.');
}

if (($core->permissions()->definitions()[0]->code ?? null) !== 'smoke.manage') {
    throw new RuntimeException('Permission registry did not expose the registered permission.');
}

$root = sys_get_temp_dir() . '/uvcms-runtime-' . bin2hex(random_bytes(6));
$templateDir = $root . '/templates/smoke';
if (!mkdir($templateDir, 0775, true) && !is_dir($templateDir)) {
    throw new RuntimeException('Unable to create smoke-test template directory.');
}

file_put_contents(
    $templateDir . '/template.html.php',
    '<?= text($title) ?>|<?= html($body) ?>|<?= isset($smokeAlias) ? "alias" : "missing" ?>',
);

$renderer = new PhpRenderer($root, $core);
$output = $renderer->render('smoke/template.html.php', [
    'title' => '🔥 <span>Title</span>',
    'body' => SafeHtml::fromTrustedStorage('<span>HTML</span>'),
]);

if ($output !== '🔥 &lt;span&gt;Title&lt;/span&gt;|<span>HTML</span>|alias') {
    throw new RuntimeException('PHP template escaping/facade smoke test failed: ' . $output);
}

$engine = new RenderEngine();
$facadeContext = new TemplateFacadeContext(
    $engine,
    [],
    new ViewTemplateRenderer($root, $core->templates()),
);
$contentSource = $core->content()->make('smoke.source', $facadeContext);
if ($contentSource->render($engine->context()) !== 'content-source') {
    throw new RuntimeException('Typed content source registry failed.');
}

$shop = new class($engine) extends RenderSource {
    public function render(RenderContext $context): string
    {
        $context->dependency('shop.product:1');
        return 'shop';
    }
};
$reviews = new class($engine) extends RenderSource {
    public function render(RenderContext $context): string
    {
        $context->dependency('reviews:product:1');
        return '+reviews';
    }
};

if ($shop->show() !== 'shop') {
    throw new RuntimeException('RenderSource::show() rendered unexpected content.');
}

if ($shop->add($reviews)->show() !== 'shop+reviews') {
    throw new RuntimeException('Composition did not render only the explicit pipeline.');
}

$dependencies = $engine->context()->dependencies();
if (!in_array('shop.product:1', $dependencies, true)
    || !in_array('reviews:product:1', $dependencies, true)
    || !in_array('smoke.source:1', $dependencies, true)) {
    throw new RuntimeException('Render metadata was not aggregated.');
}

try {
    $renderer->render('../template.html.php');
    throw new RuntimeException('Template traversal was accepted.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Template traversal was accepted.') {
        throw $exception;
    }
}

@unlink($templateDir . '/template.html.php');
@rmdir($templateDir);
@rmdir($root . '/templates');
@rmdir($root);

fwrite(STDOUT, "RUNTIME OK\n");
