<?php

declare(strict_types=1);

use Core\Delivery\Cache\FilesystemCacheStore;
use Core\Delivery\Cache\FilesystemTagIndex;
use Core\Delivery\Cache\TaggedCache;
use Core\Delivery\DeliveryInvalidatingHandler;
use Core\Delivery\Page\PageCache;
use Core\Delivery\Page\PageCacheEntry;
use Core\Delivery\ResourceHint;
use Core\Delivery\StaticPage\StaticPagePublisher;
use Core\Extension\Api\CacheApi;
use Core\Extension\Api\DeliveryApi;
use Core\Http\Request;
use Core\Http\Response;
use Core\Site\SiteContext;
use Core\View\Render\RenderContext;
use Core\View\Render\RenderResult;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$root = sys_get_temp_dir() . '/uv-page-cache-' . bin2hex(random_bytes(6));
$backend = new TaggedCache(
    new FilesystemCacheStore($root . '/cache/data'),
    new FilesystemTagIndex($root . '/cache/index'),
);
$cache = new CacheApi($backend);
$publisher = new StaticPagePublisher($root . '/static', $root . '/cache/static-index');
$delivery = new DeliveryApi($cache, $publisher);
$pageCache = new PageCache($cache, 120);

$context = new RenderContext();
$context->dependency('site:1');
$context->dependency('site:1:node:10');
$context->cacheFor(90);
$context->preload('/assets/hero.avif', ['as' => 'image', 'fetchpriority' => 'high']);
$result = new RenderResult('<h1>Site One</h1>', $context);

$entry = $pageCache->put(1, '/', 'host-a1', $result);
if (!$entry instanceof PageCacheEntry || $entry->maxAge !== 90) {
    throw new RuntimeException('Page cache did not persist cacheability metadata.');
}

$hit = $pageCache->get(1, '/', 'host-a1');
if (!$hit instanceof PageCacheEntry
    || $hit->html !== '<h1>Site One</h1>'
    || count($hit->resourceHints) !== 1) {
    throw new RuntimeException('Page cache read failed.');
}
if ($pageCache->get(1, '/', 'host-b2') !== null) {
    throw new RuntimeException('Host variants shared the same page cache entry.');
}

$blocked = new RenderContext();
$blocked->dependency('site:1');
$blocked->doNotCache('personalized');
if ($pageCache->put(1, '/account', 'host-a1', new RenderResult('private', $blocked)) !== null) {
    throw new RuntimeException('Non-cacheable render was persisted.');
}

$strict = new RenderContext();
$strict->cacheFor(600);
$strict->cacheFor(30);
if ($strict->maxAge() !== 30) {
    throw new RuntimeException('Composed max-age did not use the strictest value.');
}

$site = new SiteContext(1, 'default', 'Default', 'example.test');
$request = new Request('GET', '/', '/', [], [], [], []);
$delivery->pageCacheRule(
    'test.cart-cookie',
    static fn (Request $request, SiteContext $site): bool => !isset($request->cookies['cart']),
);
if (!$delivery->pageCacheAllowed($request, $site, false)) {
    throw new RuntimeException('Eligible public request was rejected by page-cache policy.');
}
$cartRequest = new Request('GET', '/', '/', [], [], [], ['cart' => '1']);
if ($delivery->pageCacheAllowed($cartRequest, $site, false)) {
    throw new RuntimeException('Module page-cache rule did not veto personalized request.');
}
if ($delivery->pageCacheAllowed($request, $site, true)) {
    throw new RuntimeException('Authenticated request was page-cache eligible.');
}
$queryRequest = new Request('GET', '/?q=x', '/', ['q' => 'x'], [], [], []);
if ($delivery->pageCacheAllowed($queryRequest, $site, false)) {
    throw new RuntimeException('Query-string request was page-cache eligible.');
}

$path1 = $delivery->publishStatic(
    1,
    '/catalog/',
    '<h1>Static One</h1>',
    ['site:1', 'site:1:node:10'],
);
$path2 = $delivery->publishStatic(
    2,
    '/catalog/',
    '<h1>Static Two</h1>',
    ['site:2', 'site:2:node:10'],
);
if (!is_file($path1) || !is_file($path2)) {
    throw new RuntimeException('Static publisher did not create expected files.');
}
if (is_dir($root . '/static/.index')) {
    throw new RuntimeException('Static dependency metadata leaked into the public static tree.');
}

$invalidator = new DeliveryInvalidatingHandler($delivery);
$failed = $invalidator->wrap(
    static fn (Request $request, array $variables): Response => Response::html('invalid', 422),
    static fn (Request $request, array $variables): array => ['site:1:node:10'],
);
$failed($request, []);
if ($pageCache->get(1, '/', 'host-a1') === null || !is_file($path1)) {
    throw new RuntimeException('Failed mutation invalidated delivery artifacts.');
}

$succeeded = $invalidator->wrap(
    static fn (Request $request, array $variables): Response => Response::redirect('/admin'),
    static fn (Request $request, array $variables): array => ['site:1:node:10'],
);
$succeeded($request, []);
if ($pageCache->get(1, '/', 'host-a1') !== null || is_file($path1) || !is_file($path2)) {
    throw new RuntimeException('Successful mutation did not invalidate page/static artifacts correctly.');
}

$delivery->freeze();
try {
    $delivery->pageCacheRule('late.rule', static fn (): bool => true);
    throw new RuntimeException('Frozen delivery registry accepted a late cache rule.');
} catch (LogicException $exception) {
    if ($exception->getMessage() === 'Frozen delivery registry accepted a late cache rule.') {
        throw $exception;
    }
}

$encoded = (new PageCacheEntry(
    'html',
    ['site:1'],
    [new ResourceHint('preconnect', 'https://cdn.example.test')],
    time(),
    60,
))->encode();
if (!PageCacheEntry::decode($encoded) instanceof PageCacheEntry) {
    throw new RuntimeException('Page cache entry serialization failed.');
}

$remove = static function (string $path) use (&$remove): void {
    if (!is_dir($path)) {
        if (is_file($path)) {
            @unlink($path);
        }
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $remove($path . '/' . $entry);
    }
    @rmdir($path);
};
$remove($root);

fwrite(STDOUT, "PAGE STATIC CACHE OK\n");
