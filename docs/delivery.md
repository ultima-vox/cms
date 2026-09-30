# Delivery and cache foundation

Ultima Vox uses a dependency-aware delivery layer rather than scattered module caches.

## Default backend

The default installation uses the filesystem and requires no Redis, queue worker, Node.js or external service.

```text
storage/cache/
  data/   hashed cache entries
  index/  dependency/tag index
```

Writes use temporary files plus atomic rename. Cache payloads are opaque strings encoded in JSON; the cache never unserializes PHP objects.

`RuntimeApi` accepts an optional `CacheBackendInterface`, so a future Redis implementation can replace the complete backend without changing modules.

## Extension API

Modules use:

```php
$core->cache()->get($key);
$core->cache()->put($key, $html, ['site:1', 'product:42'], 3600);
$core->cache()->remember($key, fn (): string => $html, ['product:42'], 3600);
$core->cache()->forget($key);
$core->cache()->invalidate(['product:42']);
```

Modules do not access filesystem paths or the concrete cache backend.

## Site-aware keys

Page and fragment keys must include site identity:

```php
$key = $core->cache()->pageKey($site->id, '/catalog/');
$key = $core->cache()->fragmentKey($site->id, 'reviews', 'product:42');
```

Two sites may have the same public path and content code; their cache entries must never collide.

## Tagged invalidation

Cache entries can declare dependency tags. The filesystem backend maintains a reverse index and invalidates only matching entries.

Overwriting an entry replaces its previous tag edges, so an old dependency does not invalidate a newly retagged value.

Expected tag vocabulary is compositional:

```text
site:1
site:1:node:25
site:1:infosystem:4
site:1:infosystem_item:90
product:42
```

Site-qualified tags are required whenever the underlying identity can repeat between sites.

## Render metadata

`RenderContext` collects:

- dependency tags;
- assets;
- image presets;
- structured-data providers/results;
- resource hints.

It now supports resource hints such as:

```php
$context->preload('/assets/hero.avif', [
    'as' => 'image',
    'type' => 'image/avif',
    'fetchpriority' => 'high',
]);

$context->preconnect('https://cdn.example.test', true);
```

Hints are deduplicated by their complete semantic definition.

`PhpRenderer::renderResult()` and `FrontendRenderer::renderResult()` return `RenderResult`, containing both final HTML and the `RenderContext`. Existing `render()` remains backward-compatible and returns only HTML.

Public node rendering seeds the context with site/node/layout/template dependencies before module sources render. Therefore the next page/static-cache layer can persist HTML together with a complete dependency set without re-rendering the page.

## What this layer intentionally does not do yet

This foundation does not cache every HTTP response automatically and does not write static HTML yet.

The next delivery step can safely add:

1. page-cache eligibility policy;
2. persisted render metadata;
3. publish-time/static HTML generation;
4. targeted invalidation/rebuild;
5. response/browser cache headers;
6. resource-hint emission;
7. asset hashes/minification.

Building those features on this contract avoids global cache clears and keeps future Media/Shop modules dependency-aware from the start.
