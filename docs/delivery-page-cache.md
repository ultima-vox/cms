# Page cache and static publishing

Public requests currently use the native PHP page runtime and dependency-aware page cache. `StaticPagePublisher` is an output writer exposed through `DeliveryApi`. The CLI can generate one published page through the same `PageRenderer` used by HTTP requests.

## Explicit permalink mapping

`Core\Routing\Permalink` maps an internal node path independently of PHP template names:

| Node path | Style | Public URL | Relative output file |
| --- | --- | --- | --- |
| `/` | clean or html | `/` | `index.html` |
| `/about` | clean | `/about/` | `about/index.html` |
| `/about` | html | `/about.html` | `about.html` |

The publisher adds `sites/<siteId>/` under its configured output root. The low-level API default remains `clean`; pass `permalinkStyle: 'html'` explicitly to `publishStatic()`, `staticPath()` and `deleteStatic()` for HTML output. Inputs are internal node paths, not already formatted public URLs. The root HTML mapping reserves `index.html`, so `/index` in HTML style is rejected.

All published artifacts receive `site:<siteId>` automatically, in addition to caller-supplied resource dependencies. Invalidation covers either style and remains isolated by site. The output writer rejects reserved system paths, traversal, ambiguous/encoded paths and symbolic links within the output tree. Failed deletion retains dependency metadata for retry. Output directories must remain writable only by trusted application/deployment users; filesystem checks do not provide isolation from concurrent privileged filesystem changes.

## Generate one published page

```sh
php bin/console static:publish <site-code> <node-path> [clean|html]
# For example, once the site has a primary domain:
php bin/console static:publish default /about html
```

The site editor exposes the default static publication format and an example URL/file mapping. It persists `settings.permalink_style` in the existing `sites.settings` JSONB object without replacing other settings. Missing values retain `clean`; the typed `SiteContext::staticPermalinkStyle()` rejects unsupported or non-string values. This setting controls CLI generation when its optional style argument is omitted. An explicit CLI argument overrides the default for that invocation without changing the site setting.

Site updates retain the existing `sites.manage` and CSRF checks. Successful changes invalidate the site's cache/static variants and fence pending generators; failed validation does not evict output. Older update callers that omit `permalink_style` preserve its stored value. This editor does not activate static serving or change HTTP canonical URLs, redirects, menu links or render mode. URL examples show the publication target rather than an already active canonical route.

The command requires an active site with a resolvable primary domain and a published, active node whose publication time has arrived. It boots enabled modules, resolves the page type and layout hierarchy, runs registered execution stages and publishes the resulting HTML with its render dependencies. It deliberately renders afresh rather than copying an HTTP page-cache entry. Module request-policy vetoes and render `doNotCache()` vetoes reject generation. Generation requires a session-free process. A missing page, render failure or policy veto after boot removes the selected older output variant only while its generation token is still current. Cleanup from a stale generator cannot delete a newer publication. Failures resolving the site or booting modules leave existing files alone.

`DeliveryApi::beginStaticGeneration()` must run before reading site, module and page data. Pass its token and start time to `publishRenderedStatic($siteId, $path, $result, $generation, $style)`. That method carries render dependencies, rejects non-cacheable results, and sets the deadline to generation start plus the strictest render max-age (default: 300 seconds). A render that finishes after its deadline is rejected. The caller must still apply request eligibility; the CLI applies `pageCacheAllowed()`.

The existing static tag index stores the generation token and expiry metadata outside the output tree. Publication checks the token and expiry under the same mutation lock as dependency updates and file replacement. Invalidation advances the token even when no artifact exists yet. Publication and explicit removal also advance it, so earlier generators cannot overwrite or delete their successors. The token is global to this index: unrelated site mutations or concurrent publications can conservatively reject a render; retry the entire render with a new token. This assumes all writers share the same index and committed content changes use delivery invalidation. It does not provide coordination across independent filesystems or replace database commit/invalidation discipline.

Use `DeliveryApi::readStatic()` for checked reads: expired managed output returns `null` and is removed under the index lock. `staticPath()` remains a filesystem path helper and does not authorize serving that file. To remove expired managed snapshots without reading them:

```sh
php bin/console static:prune
```

The same cleanup is available as `DeliveryApi::pruneExpiredStatic()`. Schedule the command in deployment when static output is used; no scheduler is installed automatically. Invalidation marks managed entries expired before attempting physical deletion. Deletion failures retain expired metadata for retry, so checked reads cannot return invalidated HTML. Failed atomic replacement restores the previous value's tag and expiry metadata.

The lower-level `publishStatic()` remains a trusted explicit-snapshot API with no generation token or TTL. These unmanaged/legacy snapshots are not eligible for `readStatic()` and are skipped by expiry pruning; regenerate them through the managed API before using them as cache entries. HTTP resource-hint headers are not represented in the HTML file. Direct web-server serving bypasses expiry checks and is not enabled by this change.

## Remaining integration

This path/output contract does not activate static delivery or alter current public routing. Automatic regeneration, distributed coordination, admin render-mode/permalink settings, cross-page collision detection, canonical redirects, menu/sitemap adoption and expiry-aware HTTP/static serving remain separate work. Switching policy requires an explicit migration that removes or redirects old output variants; writing both variants is not a canonical-URL policy.
