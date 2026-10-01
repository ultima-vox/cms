<?php

declare(strict_types=1);

use Core\Database;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Http\Request;
use Core\Layout\LayoutDefinition;
use Core\Layout\LayoutExecutionStage;
use Core\Page\PageExecutionChain;
use Core\Page\PageExecutionContext;
use Core\Page\PageExecutorInterface;
use Core\Page\PageExecutorStage;
use Core\Page\PageRuntime;
use Core\Site\SiteContext;
use Core\View\PhpRenderer;
use Core\View\Render\RenderContext;
use Core\View\SafeHtml;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$root = sys_get_temp_dir() . '/uvcms-layout-stage-' . bin2hex(random_bytes(6));
$layoutDirectory = $root . '/templates/layouts';
if (!mkdir($layoutDirectory, 0775, true) && !is_dir($layoutDirectory)) {
    throw new RuntimeException('Unable to create layout execution smoke directory.');
}

$outerFile = $layoutDirectory . '/outer.php';
$innerFile = $layoutDirectory . '/inner.php';

try {
    file_put_contents(
        $outerFile,
        '<!doctype html><html><body><div class="outer"><?= $page->execute() ?></div></body></html>',
    );
    file_put_contents(
        $innerFile,
        '<section class="inner" data-layout="<?= text($layout->name) ?>"><?= $page->execute() ?></section>',
    );

    $core = new ExtensionCore(new RuntimeApi(Database::connection(), $root));
    $renderer = new PhpRenderer($root, $core);
    $renderContext = new RenderContext();
    $pageContext = new PageExecutionContext(
        new Request(
            method: 'GET',
            uri: '/demo/',
            path: '/demo/',
            query: [],
            post: [],
            server: [],
            cookies: [],
        ),
        new SiteContext(1, 'default', 'Default site', 'example.test'),
        ['id' => 77, 'name' => 'Demo', 'title' => 'Demo page', 'path' => '/demo/'],
        [],
        $renderContext,
    );

    $terminal = new class implements PageExecutorInterface {
        public function execute(PageExecutionContext $context): SafeHtml
        {
            $context->renderContext->dependency('terminal:layout-smoke');

            return SafeHtml::fromTrustedStorage('<article>terminal</article>');
        }
    };

    $page = new PageRuntime(
        $pageContext,
        new PageExecutionChain([
            new LayoutExecutionStage(
                new LayoutDefinition(101, null, 'Outer', 'layouts/outer.php'),
                $renderer,
            ),
            new LayoutExecutionStage(
                new LayoutDefinition(102, 101, 'Inner', 'layouts/inner.php'),
                $renderer,
            ),
            new PageExecutorStage($terminal),
        ]),
    );

    $html = $page->start()->value();
    $expected = '<!doctype html><html><body><div class="outer"><section class="inner" data-layout="Inner"><article>terminal</article></section></div></body></html>';
    if ($html !== $expected) {
        throw new RuntimeException('Native PHP layout execution rendered unexpected HTML: ' . $html);
    }

    foreach ([
        'layout:101',
        'layout:102',
        'template:layouts/outer.php',
        'template:layouts/inner.php',
        'terminal:layout-smoke',
    ] as $dependency) {
        if (!in_array($dependency, $renderContext->dependencies(), true)) {
            throw new RuntimeException('Layout execution dependency is missing: ' . $dependency);
        }
    }
} finally {
    @unlink($outerFile);
    @unlink($innerFile);
    @rmdir($layoutDirectory);
    @rmdir($root . '/templates');
    @rmdir($root);
}

fwrite(STDOUT, "LAYOUT EXECUTION STAGE OK\n");
