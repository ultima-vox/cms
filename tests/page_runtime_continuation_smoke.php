<?php

declare(strict_types=1);

use Core\Http\Request;
use Core\Page\PageExecutionChain;
use Core\Page\PageExecutionContext;
use Core\Page\PageExecutionStageInterface;
use Core\Page\PageExecutorInterface;
use Core\Page\PageExecutorStage;
use Core\Page\PageRuntime;
use Core\Site\SiteContext;
use Core\View\Render\RenderContext;
use Core\View\SafeHtml;

require dirname(__DIR__) . '/vendor/autoload.php';

$renderContext = new RenderContext();
$context = new PageExecutionContext(
    new Request(
        method: 'GET',
        uri: '/catalog/demo/',
        path: '/catalog/demo/',
        query: [],
        post: [],
        server: [],
        cookies: [],
    ),
    new SiteContext(1, 'default', 'Default site', 'example.test'),
    [
        'id' => 42,
        'name' => 'Demo page',
        'title' => 'Demo title',
        'path' => '/catalog/demo/',
    ],
    ['view' => 'demo.default'],
    $renderContext,
);

$terminalExecutor = new class implements PageExecutorInterface {
    public function execute(PageExecutionContext $context): SafeHtml
    {
        $context->renderContext->dependency('terminal:demo');

        return SafeHtml::fromTrustedStorage('<article>terminal</article>');
    }
};

$outerStage = new class implements PageExecutionStageInterface {
    public function execute(PageRuntime $page): SafeHtml
    {
        $inner = $page->execute();

        return SafeHtml::fromTrustedStorage('<div class="outer">' . $inner->value() . '</div>');
    }
};

$innerStage = new class implements PageExecutionStageInterface {
    public function execute(PageRuntime $page): SafeHtml
    {
        $inner = $page->execute();

        return SafeHtml::fromTrustedStorage('<section class="inner">' . $inner->value() . '</section>');
    }
};

$chain = new PageExecutionChain([
    $outerStage,
    $innerStage,
    new PageExecutorStage($terminalExecutor),
]);
$page = new PageRuntime($context, $chain);

if ($page->id() !== 42
    || $page->name() !== 'Demo page'
    || $page->title() !== 'Demo title'
    || $page->path() !== '/catalog/demo/'
    || $page->setting('view') !== 'demo.default') {
    throw new RuntimeException('Page runtime metadata access is invalid.');
}

$html = $page->start();
$expected = '<div class="outer"><section class="inner"><article>terminal</article></section></div>';
if ($html->value() !== $expected) {
    throw new RuntimeException('Page execution chain rendered an unexpected result: ' . $html->value());
}

if (!in_array('terminal:demo', $renderContext->dependencies(), true)) {
    throw new RuntimeException('Page execution chain did not preserve the shared RenderContext.');
}

try {
    $page->start();
    throw new RuntimeException('Page execution chain allowed a second start.');
} catch (LogicException $exception) {
    if ($exception->getMessage() === 'Page execution chain allowed a second start.') {
        throw $exception;
    }
}

$doubleContinueStage = new class implements PageExecutionStageInterface {
    public function execute(PageRuntime $page): SafeHtml
    {
        $page->execute();

        return $page->execute();
    }
};
$noopTerminal = new class implements PageExecutionStageInterface {
    public function execute(PageRuntime $page): SafeHtml
    {
        unset($page);

        return SafeHtml::fromTrustedStorage('terminal');
    }
};
$doubleRuntime = new PageRuntime(
    $context,
    new PageExecutionChain([$doubleContinueStage, $noopTerminal]),
);

try {
    $doubleRuntime->start();
    throw new RuntimeException('Page execution stage continued the chain twice.');
} catch (LogicException $exception) {
    if ($exception->getMessage() === 'Page execution stage continued the chain twice.') {
        throw $exception;
    }
}

$outsideRuntime = new PageRuntime($context, new PageExecutionChain([$noopTerminal]));
try {
    $outsideRuntime->execute();
    throw new RuntimeException('Page continuation was available outside an active stage.');
} catch (LogicException $exception) {
    if ($exception->getMessage() === 'Page continuation was available outside an active stage.') {
        throw $exception;
    }
}

try {
    new PageExecutionChain(array_fill(0, 33, $noopTerminal));
    throw new RuntimeException('Page execution chain accepted excessive nesting depth.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Page execution chain accepted excessive nesting depth.') {
        throw $exception;
    }
}

require __DIR__ . '/layout_execution_stage_smoke.php';

fwrite(STDOUT, "PAGE RUNTIME CONTINUATION OK\n");
