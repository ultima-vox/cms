<?php

declare(strict_types=1);

use Core\Delivery\Cache\FilesystemCacheStore;
use Core\Delivery\Cache\TaggedCache;
use Core\View\Render\RenderContext;
use Core\View\Render\RenderEngine;
use Core\View\Render\RenderResult;
use Core\View\Render\RenderSource;
use Core\View\Render\TemplateFacadeContext;
use Core\View\Render\ViewTemplateRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = sys_get_temp_dir() . '/cms-runtime-' . bin2hex(random_bytes(4));
$templateDir = $root . '/templates';
if (!mkdir($templateDir, 0775, true) && !is_dir($templateDir)) {
    throw new RuntimeException('Unable to create runtime smoke directory.');
}

file_put_contents(
    $templateDir . '/template.html.php',
    <<<'PHP'
<?php
/** @var \Core\View\Render\RenderResult $result */
?>
<section><?= htmlspecialchars((string) $result->data['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></section>
PHP,
);

$cacheRoot = $root . '/cache';
$store = new FilesystemCacheStore($cacheRoot);
$cache = new TaggedCache($store);
$context = new RenderContext(1, '/', $cache);
$engine = new RenderEngine($context);
$renderer = new ViewTemplateRenderer($templateDir);

$source = new RenderSource(
    $engine,
    static function (array $params, RenderContext $context): RenderResult {
        $context->dependOn('smoke.source:1');
        return new RenderResult([
            'message' => (string) ($params['message'] ?? 'ok'),
        ]);
    },
    ['message' => 'runtime'],
    $renderer,
);

$html = $source->template('template.html.php')->show();
if (!str_contains($html, '<section>runtime</section>')) {
    throw new RuntimeException('RenderSource template rendering failed.');
}

$facades = new TemplateFacadeContext([
    'shop' => static function (array $params, RenderContext $context): RenderSource {
        return new RenderSource(
            new RenderEngine($context),
            static function (array $inner, RenderContext $innerContext): RenderResult {
                $innerContext->dependOn('shop.product:1');
                return new RenderResult(['value' => 'shop']);
            },
            $params,
        );
    },
    'reviews' => static function (array $params, RenderContext $context): RenderSource {
        return new RenderSource(
            new RenderEngine($context),
            static function (array $inner, RenderContext $innerContext): RenderResult {
                $innerContext->dependOn('reviews:product:1');
                return new RenderResult(['value' => 'reviews']);
            },
            $params,
        );
    },
], $context);

$shop = $facades->call('shop', ['id' => 1]);
$reviews = $facades->call('reviews', ['product' => 1]);

$renderValue = static function (RenderResult $result): string {
    return (string) ($result->data['value'] ?? '');
};
$shop = $shop->map(static fn (RenderResult $result): string => $renderValue($result));
$reviews = $reviews->map(static fn (RenderResult $result): string => $renderValue($result));

$shop = $shop->map(static fn (string $value): string => $value);
$reviews = $reviews->map(static fn (string $value): string => $value);

$shop = new RenderSource(
    $engine,
    static function (array $params, RenderContext $ctx): RenderResult {
        $ctx->dependOn('shop.product:1');
        return new RenderResult(['value' => 'shop']);
    },
);
$reviews = new RenderSource(
    $engine,
    static function (array $params, RenderContext $ctx): RenderResult {
        $ctx->dependOn('reviews:product:1');
        return new RenderResult(['value' => 'reviews']);
    },
);

$shop = $shop->map(static fn (RenderResult $result): string => (string) $result->data['value']);
$reviews = $reviews->map(static fn (RenderResult $result): string => (string) $result->data['value']);

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

require __DIR__ . '/template_linter_smoke.php';
require __DIR__ . '/core_module_boundary_smoke.php';
require __DIR__ . '/delivery_cache_smoke.php';
require __DIR__ . '/page_static_cache_smoke.php';
require __DIR__ . '/module_package_installer_smoke.php';

fwrite(STDOUT, "RUNTIME OK\n");
