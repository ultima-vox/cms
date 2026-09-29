<?php

declare(strict_types=1);

use Core\Extension\Core as ExtensionCore;
use Core\View\PhpRenderer;
use Core\View\Render\RenderContext;
use Core\View\Render\RenderEngine;
use Core\View\Render\RenderSource;
use Core\View\SafeHtml;

require dirname(__DIR__) . '/vendor/autoload.php';

$core = new ExtensionCore();
$core->routes()->get('/smoke', 'smoke.route', static fn (): null => null);
$core->extensions()->register('smoke.point', 'smoke.extension', new stdClass());
$core->freeze();

try {
    $core->routes()->get('/late', 'late.route', static fn (): null => null);
    throw new RuntimeException('Frozen route registry accepted a late registration.');
} catch (LogicException) {
    // Expected.
}

$root = sys_get_temp_dir() . '/uvcms-runtime-' . bin2hex(random_bytes(6));
$templateDir = $root . '/templates/smoke';
if (!mkdir($templateDir, 0775, true) && !is_dir($templateDir)) {
    throw new RuntimeException('Unable to create smoke-test template directory.');
}

file_put_contents(
    $templateDir . '/template.html.php',
    '<?= text($title) ?>|<?= html($body) ?>',
);

$renderer = new PhpRenderer($root, $core);
$output = $renderer->render('smoke/template.html.php', [
    'title' => '🔥 <span>Title</span>',
    'body' => SafeHtml::fromTrustedStorage('<span>HTML</span>'),
]);

if ($output !== '🔥 &lt;span&gt;Title&lt;/span&gt;|<span>HTML</span>') {
    throw new RuntimeException('PHP template escaping smoke test failed: ' . $output);
}

$engine = new RenderEngine();
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
if (!in_array('shop.product:1', $dependencies, true) || !in_array('reviews:product:1', $dependencies, true)) {
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
