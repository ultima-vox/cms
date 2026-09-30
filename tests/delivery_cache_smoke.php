<?php

declare(strict_types=1);

use Core\Delivery\Cache\CacheKey;
use Core\Delivery\Cache\FilesystemCacheStore;
use Core\Delivery\Cache\FilesystemTagIndex;
use Core\Delivery\Cache\TaggedCache;
use Core\Extension\Api\CacheApi;
use Core\View\Render\RenderContext;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = sys_get_temp_dir() . '/uv-delivery-' . bin2hex(random_bytes(6));
$backend = new TaggedCache(
    new FilesystemCacheStore($root . '/data'),
    new FilesystemTagIndex($root . '/index'),
);
$cache = new CacheApi($backend);

$site1 = CacheKey::page(1, '/catalog/');
$site2 = CacheKey::page(2, '/catalog/');
if ($site1 === $site2) {
    throw new RuntimeException('Page cache keys are not site-aware.');
}

$cache->put($site1, 'site-1', ['site:1', 'site:1:node:10']);
$cache->put($site2, 'site-2', ['site:2', 'site:2:node:10']);
if ($cache->get($site1) !== 'site-1' || $cache->get($site2) !== 'site-2') {
    throw new RuntimeException('Filesystem cache read/write failed.');
}

if ($cache->invalidate(['site:1:node:10']) !== 1
    || $cache->get($site1) !== null
    || $cache->get($site2) !== 'site-2') {
    throw new RuntimeException('Tagged site-scoped invalidation failed.');
}

$cache->put($site2, 'retagged', ['old-tag']);
$cache->put($site2, 'retagged-v2', ['new-tag']);
if ($cache->invalidate(['old-tag']) !== 0 || $cache->get($site2) !== 'retagged-v2') {
    throw new RuntimeException('Tag replacement retained a stale invalidation edge.');
}
if ($cache->invalidate(['new-tag']) !== 1 || $cache->get($site2) !== null) {
    throw new RuntimeException('Replacement tag invalidation failed.');
}

$resolverCalls = 0;
$fragment = $cache->fragmentKey(1, 'reviews', 'product:42');
$first = $cache->remember(
    $fragment,
    static function () use (&$resolverCalls): string {
        $resolverCalls++;
        return 'fragment-html';
    },
    ['site:1', 'product:42'],
    30,
);
$second = $cache->remember(
    $fragment,
    static function () use (&$resolverCalls): string {
        $resolverCalls++;
        return 'unexpected';
    },
    ['site:1', 'product:42'],
    30,
);
if ($first !== 'fragment-html' || $second !== 'fragment-html' || $resolverCalls !== 1) {
    throw new RuntimeException('Cache remember semantics failed.');
}

$ttl = $cache->fragmentKey(1, 'ttl', 'short');
$cache->put($ttl, 'short-lived', ['ttl-test'], 1);
sleep(2);
if ($cache->get($ttl) !== null) {
    throw new RuntimeException('Cache TTL expiration failed.');
}

$context = new RenderContext();
$context->dependency('site:1');
$context->dependency('site:1');
$context->preload('/assets/hero.avif', [
    'as' => 'image',
    'type' => 'image/avif',
    'fetchpriority' => 'high',
]);
$context->preload('/assets/hero.avif', [
    'as' => 'image',
    'type' => 'image/avif',
    'fetchpriority' => 'high',
]);
$context->preconnect('https://cdn.example.test', true);

if ($context->dependencies() !== ['site:1'] || count($context->resourceHints()) !== 2) {
    throw new RuntimeException('Render dependency/resource-hint deduplication failed.');
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

fwrite(STDOUT, "DELIVERY CACHE OK\n");
