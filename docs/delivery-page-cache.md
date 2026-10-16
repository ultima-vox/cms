# Page cache and static publishing

Public requests currently use the native PHP page runtime and dependency-aware page cache. `StaticPagePublisher` is an output writer exposed through `DeliveryApi`; it does not yet generate a page by resolving a node itself.

## Explicit permalink mapping

`Core\Routing\Permalink` maps an internal node path independently of PHP template names:

| Node path | Style | Public URL | Relative output file |
| --- | --- | --- | --- |
| `/` | clean or html | `/` | `index.html` |
| `/about` | clean | `/about/` | `about/index.html` |
| `/about` | html | `/about.html` | `about.html` |

The publisher adds `sites/<siteId>/` under its configured output root. The existing default remains `clean`; pass `permalinkStyle: 'html'` explicitly to `publishStatic()`, `staticPath()` and `deleteStatic()` for HTML output. Inputs are internal node paths, not already formatted public URLs. The root HTML mapping reserves `index.html`, so `/index` in HTML style is rejected.

All published artifacts receive `site:<siteId>` automatically, in addition to caller-supplied resource dependencies. Invalidation covers either style and remains isolated by site. The output writer rejects reserved system paths, traversal, ambiguous/encoded paths and symbolic links within the output tree. Failed deletion retains dependency metadata for retry. Output directories must remain writable only by trusted application/deployment users; filesystem checks do not provide isolation from concurrent privileged filesystem changes.

## Remaining integration

This path/output contract does not activate static delivery or alter current public routing. Automatic rendering/publishing through the shared page pipeline, admin render-mode/permalink settings, cross-page collision detection, canonical redirects, menu/sitemap adoption and web-server static serving are not implemented by the writer. Switching policy requires an explicit migration that removes or redirects old output variants; writing both variants is not a canonical-URL policy.
