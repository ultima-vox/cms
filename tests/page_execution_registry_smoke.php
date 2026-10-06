<?php

declare(strict_types=1);

use Core\Extension\Api\PagesApi;
use Core\Http\Request;
use Core\Page\PageConfigurationValidatorInterface;
use Core\Page\PageExecutionContext;
use Core\Page\PageExecutionStageInterface;
use Core\Page\PageExecutionStageProviderInterface;
use Core\Page\PageExecutorInterface;
use Core\Page\PageRuntime;
use Core\Page\PageTypeDefinition;
use Core\Page\PageTypeProvisionerInterface;
use Core\Page\PageTypeProvisioningContext;
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
$validator = new class implements PageConfigurationValidatorInterface {
    public function validate(array $configuration): array
    {
        $limit = $configuration['limit'] ?? 10;
        if (is_string($limit) && ctype_digit($limit)) {
            $limit = (int) $limit;
        }
        if (!is_int($limit) || $limit < 1 || $limit > 100) {
            throw new RuntimeException('Smoke page limit is invalid.');
        }

        return ['limit' => $limit];
    }
};
$provisioner = new class implements PageTypeProvisionerInterface {
    public function provision(PageTypeProvisioningContext $context): array
    {
        if ($context->siteId !== 1
            || $context->nodeId !== 10
            || $context->nodeName !== 'Demo'
            || $context->nodePath !== '/demo/') {
            throw new RuntimeException('Page type provisioner received an unexpected context.');
        }

        return [
            'limit' => $context->requestedConfiguration['limit'] ?? '12',
        ];
    }
};
$definition = new PageTypeDefinition(
    code: 'SMOKE.PAGE',
    name: 'Smoke page',
    executor: $executor,
    configurationSchema: [
        'type' => 'object',
        'properties' => [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
        ],
        'additionalProperties' => false,
    ],
    configurationValidator: $validator,
    provisioner: $provisioner,
    isDefault: true,
    sorting: 20,
    description: 'Smoke page type.',
);
$pages->type($definition);

$pages->stageProvider(
    'smoke.wrapper',
    new class implements PageExecutionStageProviderInterface {
        public function stages(PageExecutionContext $context): array
        {
            return [
                new class implements PageExecutionStageInterface {
                    public function execute(PageRuntime $page): SafeHtml
                    {
                        return SafeHtml::fromTrustedStorage(
                            '<aside>before</aside>' . $page->execute()->value(),
                        );
                    }
                },
            ];
        }
    },
    10,
);

if (!$pages->has('SMOKE.PAGE')) {
    throw new RuntimeException('Page type registry did not normalize type codes.');
}

if ($pages->definition('smoke.page') !== $definition || $pages->resolve('smoke.page') !== $executor) {
    throw new RuntimeException('Page type registry returned an unexpected definition or executor.');
}

if ($pages->default() !== $definition) {
    throw new RuntimeException('Page type registry did not expose the registered default type.');
}

$normalized = $pages->validateConfiguration('smoke.page', ['limit' => '25']);
if ($normalized !== ['limit' => 25]) {
    throw new RuntimeException('Page type configuration validator did not normalize configuration.');
}

$provisioned = $pages->provisionConfiguration(
    'smoke.page',
    new PageTypeProvisioningContext(
        siteId: 1,
        nodeId: 10,
        nodeName: 'Demo',
        nodeTitle: 'Demo title',
        nodePath: '/demo/',
        requestedConfiguration: ['limit' => '30'],
    ),
);
if ($provisioned !== ['limit' => 30]) {
    throw new RuntimeException('Page type provisioner result was not validated and normalized.');
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
    $normalized,
    $renderContext,
);

$html = $pages->resolve('smoke.page')->execute($context);
if ($html->value() !== '<p>page-executor-ok</p>') {
    throw new RuntimeException('Page executor returned unexpected HTML.');
}

$providerStages = $pages->stages($context);
if (count($providerStages) !== 1) {
    throw new RuntimeException('Page stage provider registry returned an unexpected stage count.');
}
$providerChain = new \Core\Page\PageExecutionChain([
    ...$providerStages,
    new \Core\Page\PageExecutorStage($executor),
]);
$providerRuntime = new PageRuntime($context, $providerChain);
if ($providerRuntime->start()->value() !== '<aside>before</aside><p>page-executor-ok</p>') {
    throw new RuntimeException('Page stage provider did not compose around the page executor.');
}

if (!in_array('page-executor:smoke', $renderContext->dependencies(), true)) {
    throw new RuntimeException('Page executor did not contribute to the shared render context.');
}

try {
    $pages->type($definition);
    throw new RuntimeException('Page type registry accepted a duplicate type code.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Page type registry accepted a duplicate type code.') {
        throw $exception;
    }
}

try {
    $pages->type(new PageTypeDefinition(
        code: 'second.default',
        name: 'Second default',
        executor: $executor,
        isDefault: true,
    ));
    throw new RuntimeException('Page type registry accepted a second default type.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Page type registry accepted a second default type.') {
        throw $exception;
    }
}

try {
    $pages->definition('missing.page');
    throw new RuntimeException('Page type registry resolved an unknown type code.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Page type registry resolved an unknown type code.') {
        throw $exception;
    }
}

$pages->executor('legacy.shorthand', $executor);
$definitions = $pages->definitions();
if (count($definitions) !== 2
    || $definitions[0]->code !== 'legacy.shorthand'
    || $definitions[1]->code !== 'smoke.page') {
    throw new RuntimeException('Page type definitions were not exposed in deterministic order.');
}

$pages->freeze();

try {
    $pages->executor('late.page', $executor);
    throw new RuntimeException('Frozen page type registry accepted late registration.');
} catch (LogicException $exception) {
    if ($exception->getMessage() === 'Frozen page type registry accepted late registration.') {
        throw $exception;
    }
}

require __DIR__ . '/page_type_selection_smoke.php';
require __DIR__ . '/page_runtime_continuation_smoke.php';

fwrite(STDOUT, "PAGE EXECUTION REGISTRY OK\n");
