<?php

declare(strict_types=1);

use Core\Extension\Api\PagesApi;
use Core\Http\Request;
use Core\Page\PageExecutionContext;
use Core\Page\PageExecutorInterface;
use Core\Site\SiteContext;
use Core\View\Render\RenderContext;
use Core\View\SafeHtml;

require dirname(__DIR__) . '/vendor/autoload.php';

$pages = new PagesApi();
$executor = new class implements PageExecutorInterface {
    public function execute(PageExecutionContext $context): SafeHtml
    {
        $context->renderContext->dependency('page-executor:smoke');

        return SafeHtml::fromTrustedStorage('<p>page-executor-ok</p>');
    }
};

$pages->executor('smoke.page', $executor);

if (!$pages->has('SMOKE.PAGE')) {
    throw new RuntimeException('Page executor registry did not normalize executor codes.');
}

if ($pages->resolve('smoke.page') !== $executor) {
    throw new RuntimeException('Page executor registry returned an unexpected executor instance.');
}

$renderContext = new RenderContext();
$context = new PageExecutionContext(
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
    ['id' => 10, 'path' => '/demo/'],
    ['view' => 'smoke.default'],
    $renderContext,
);

$html = $pages->resolve('smoke.page')->execute($context);
if ($html->value() !== '<p>page-executor-ok</p>') {
    throw new RuntimeException('Page executor returned unexpected HTML.');
}

if (!in_array('page-executor:smoke', $renderContext->dependencies(), true)) {
    throw new RuntimeException('Page executor did not contribute to the shared render context.');
}

try {
    $pages->executor('smoke.page', $executor);
    throw new RuntimeException('Page executor registry accepted a duplicate executor code.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Page executor registry accepted a duplicate executor code.') {
        throw $exception;
    }
}

try {
    $pages->resolve('missing.page');
    throw new RuntimeException('Page executor registry resolved an unknown executor code.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Page executor registry resolved an unknown executor code.') {
        throw $exception;
    }
}

$pages->freeze();

try {
    $pages->executor('late.page', $executor);
    throw new RuntimeException('Frozen page executor registry accepted late registration.');
} catch (LogicException $exception) {
    if ($exception->getMessage() === 'Frozen page executor registry accepted late registration.') {
        throw $exception;
    }
}

require __DIR__ . '/page_type_selection_smoke.php';
require __DIR__ . '/page_runtime_continuation_smoke.php';

fwrite(STDOUT, "PAGE EXECUTION REGISTRY OK\n");
