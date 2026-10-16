# Page cache and static publishing

Public requests currently use the native PHP page runtime and dependency-aware page cache. `StaticPagePublisher` is an output writer exposed through `DeliveryApi`. The CLI can generate one published page through the same `PageRenderer` used by HTTP requests.

## Explicit permalink mapping

`Core\Routing\Permalink` maps an internal node path independently of PHP template names:

| Node path | Style | Public URL | Relative output file |
| --- | --- | --- | --- |
| `/` | clean or html | `/` | `index.html` |
| `/about` | clean | `/about/` | `about/index.html` |
| `/about` | html | `/about.html` | `about.html` |

The publisher adds `sites/<siteId>/` under its configured output root. The existing default remains `clean`; pass `permalinkStyle: 'html'` explicitly to `publishStatic()`, `staticPath()` and `deleteStatic()` for HTML output. Inputs are internal node paths, not already formatted public URLs. The root HTML mapping reserves `index.html`, so `/index` in HTML style is rejected.

All published artifacts receive `site:<siteId>` automatically, in addition to caller-supplied resource dependencies. Invalidation covers either style and remains isolated by site. The output writer rejects reserved system paths, traversal, ambiguous/encoded paths and symbolic links within the output tree. Failed deletion retains dependency metadata for retry. Output directories must remain writable only by trusted application/deployment users; filesystem checks do not provide isolation from concurrent privileged filesystem changes.

## Generate one published page

```sh
php bin/console static:publish <site-code> <node-path> [clean|html]
# For example, once the site has a primary domain:
php bin/console static:publish default /about html
```

The command requires an active site with a resolvable primary domain and a published, active node whose publication time has arrived. It boots enabled modules, resolves the page type and layout hierarchy, runs registered execution stages and publishes the resulting HTML with its render dependencies. It deliberately renders afresh rather than copying an HTTP page-cache entry. Module request-policy vetoes and render `doNotCache()` vetoes reject generation. Generation requires a session-free process. A missing page, render failure or policy veto after boot removes the selected older output variant; failures resolving the site or booting modules leave existing files alone.

`DeliveryApi::publishRenderedStatic()` carries `RenderResult` dependencies and rejects non-cacheable results. Its caller must apply request eligibility before rendering; the CLI applies `pageCacheAllowed()`. The lower-level `publishStatic()` remains available for trusted callers supplying their own HTML and dependencies.

These files are explicit snapshots, not automatically expiring cache entries. Render max-age and HTTP resource-hint headers are not enforced by the filesystem writer. Generation and content mutation are not one transaction: concurrent publication can race invalidation, so generate snapshots during a coordinated content freeze until generation fencing exists. Do not expose them as a live static cache without expiry, host/canonical-URL policy and regeneration coordination.

## Remaining integration

This path/output contract does not activate static delivery or alter current public routing. Automatic regeneration, expiry/generation coordination, admin render-mode/permalink settings, cross-page collision detection, canonical redirects, menu/sitemap adoption and web-server static serving remain separate work. Switching policy requires an explicit migration that removes or redirects old output variants; writing both variants is not a canonical-URL policy.
