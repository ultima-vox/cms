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
use Core\Routing\Permalink;
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

$assertStaticRejected = static function (callable $operation): void {
    try {
        $operation();
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException('Unsafe static publication operation was accepted.');
};
foreach ([
    ['/', 'clean', '/', 'index.html'],
    ['/', 'html', '/', 'index.html'],
    ['/about', 'clean', '/about/', 'about/index.html'],
    ['/about/', 'html', '/about.html', 'about.html'],
    ['/catalog/model-5', 'html', '/catalog/model-5.html', 'catalog/model-5.html'],
] as [$nodePath, $style, $expectedUrl, $expectedFile]) {
    $permalink = new Permalink($nodePath, $style);
    if ($permalink->url() !== $expectedUrl || $permalink->relativeFile() !== $expectedFile) {
        throw new RuntimeException('Permalink URL and output file mapping disagree.');
    }
}
foreach (['/../escape', '/a/./b', '/a//b', '/a%2fb', '/a?x=1', '/a#fragment', '/a\\b', '/admin', '/api/x', '/health', '/assets/x', '/storage/x', '/media/x'] as $unsafePath) {
    $assertStaticRejected(fn () => $delivery->publishStatic(1, $unsafePath, 'unsafe', []));
}
$assertStaticRejected(fn () => $delivery->publishStatic(1, '/about', 'unsafe', [], 'unknown'));
$assertStaticRejected(fn () => $delivery->publishStatic(1, '/index', 'unsafe', [], 'html'));
$assertStaticRejected(fn () => $delivery->publishStatic(1, '/' . str_repeat('a', 251), 'unsafe', [], 'html'));

// Both styles must receive the site dependency even if a caller supplies no tags.
$cleanFile = $delivery->publishStatic(1, '/article', 'Clean', []);
$htmlFile = $delivery->publishStatic(1, '/article', 'HTML', [], 'html');
$otherFile = $delivery->publishStatic(2, '/article', 'Other site', [], 'html');
if (!str_ends_with($cleanFile, '/sites/1/article/index.html')
    || !str_ends_with($htmlFile, '/sites/1/article.html')
    || $delivery->staticPath(1, '/article', 'html') !== $htmlFile
    || file_get_contents($cleanFile) !== 'Clean' || file_get_contents($htmlFile) !== 'HTML') {
    throw new RuntimeException('Static publisher did not honor the explicit permalink style.');
}
$delivery->invalidate(['site:1']);
if (is_file($cleanFile) || is_file($htmlFile) || !is_file($otherFile)) {
    throw new RuntimeException('Automatic static site dependencies failed to isolate invalidation.');
}
$otherClean = $delivery->publishStatic(2, '/article', 'Other clean', []);
$delivery->deleteStatic(2, '/article', 'html');
if (is_file($otherFile) || !is_file($otherClean)) {
    throw new RuntimeException('Deleting an HTML permalink removed the wrong output variant.');
}
$dotted = $delivery->publishStatic(1, '/name..with-dots', 'Dotted slug', []);
$delivery->invalidate(['site:1']);
if (is_file($dotted)) {
    throw new RuntimeException('A harmless dotted slug escaped static invalidation.');
}

// A path inside the output root must never traverse an existing symbolic link.
$outside = $root . '/outside';
mkdir($outside, 0775, true);
$escapeLink = $root . '/static/sites/1/escape';
symlink($outside, $escapeLink);
try {
    $assertStaticRejected(fn () => $delivery->publishStatic(1, '/escape/file', 'unsafe', []));
    if (file_exists($outside . '/file/index.html')) {
        throw new RuntimeException('Static publisher wrote through a symbolic link.');
    }
} finally {
    unlink($escapeLink);
}
$guarded = $delivery->publishStatic(1, '/guard/item', 'Retain until safely deleted', []);
$guardDirectory = dirname($guarded);
$movedDirectory = $root . '/moved-output';
rename($guardDirectory, $movedDirectory);
symlink($movedDirectory, $guardDirectory);
try {
    $assertStaticRejected(fn () => $delivery->invalidate(['site:1']));
    if (!is_file($movedDirectory . '/index.html')) {
        throw new RuntimeException('Static invalidation deleted a file through a symbolic link.');
    }
} finally {
    unlink($guardDirectory);
    rename($movedDirectory, $guardDirectory);
}
$retried = $delivery->invalidate(['site:1']);
if ($retried['static'] !== 1 || is_file($guarded)) {
    throw new RuntimeException('Failed static deletion discarded dependency metadata needed for retry.');
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
