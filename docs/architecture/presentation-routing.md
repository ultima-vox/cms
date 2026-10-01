# Presentation and Routing Contract

## Canonical rule

Ultima Vox CMS uses native PHP templates internally and technology-neutral public URLs.

> **Internal templates use ordinary `.php` files. Public URLs never expose PHP implementation details.**

> **Static rendering and permalink format are independent settings.**

This is a project invariant.

## Internal templates

Presentation files are ordinary PHP files containing HTML and small presentation-only PHP expressions.

Examples:

```text
templates/layouts/main.php
templates/pages/default.php
modules/infosystem/templates/public/list.php
modules/infosystem/templates/public/item.php
```

Twig, Blade, XSL/XML and custom template languages are not part of the Ultima Vox presentation contract.

Templates must not contain business logic or database queries. Controllers, application services and module facades prepare DTOs/ViewModels before rendering.

The template filename is an implementation detail and must never determine or leak into the public URL.

## Public URLs

Public paths are defined by site structure and routing, not by PHP filenames.

Canonical examples:

```text
/
/about/
/catalog/
/catalog/septiki/
/catalog/septiki/model-5/
/news/company-launched-new-line/
```

The CMS must not require implementation-style URLs such as:

```text
/index.php?page=12
/catalog.php?id=42
/news.php?action=view&id=10
```

Query parameters remain valid for concerns where they are semantically appropriate, such as search, sorting or faceted filtering:

```text
/search/?q=pump
/catalog/?sort=price
/catalog/?brand=acme&price_from=10000
```

## Rendering modes

A node/page can be rendered dynamically or published statically.

### Dynamic

The public request is routed through `public/index.php`, resolved to a node/handler and rendered using the configured PHP template.

Example public URL:

```text
/catalog/septiki/model-5/
```

PHP is used at request time, but `.php` is never present in the URL.

### Static

The CMS renders the page through the same PHP presentation pipeline ahead of time and writes the resulting HTML to the static publishing target.

The web server serves the generated HTML directly without PHP application boot for that request.

Static publishing must therefore be an optimization/deployment mode, not a separate template system.

## Static permalink modes

Ultima Vox supports both static permalink styles:

### Clean directory permalink

Public URL:

```text
/about/
```

Generated target:

```text
/about/index.html
```

### HTML permalink

Public URL:

```text
/about.html
```

Generated target:

```text
/about.html
```

Both modes are first-class and are selectable from the administration UI.

The chosen permalink style must not change the PHP template used to render the page.

## Administration contract

The administration UI must expose permalink/static publication policy explicitly rather than deriving it from a template filename.

At minimum the site/page configuration model must be able to represent:

```text
render_mode: dynamic | static
permalink_style: clean | html
```

The exact persistence model may evolve, but these concerns must remain independent.

Recommended behavior:

- site-level default permalink style;
- optional page/node override where justified;
- preview of the resulting public URL in the editor;
- collision validation before publish;
- canonical URL generation based on the resolved public path;
- automatic invalidation/republication after content or template changes.

## Web-server contract

For clean static URLs the web server may resolve:

```text
/about/ -> /about/index.html
```

For HTML static URLs it may resolve directly:

```text
/about.html -> /about.html
```

Dynamic fallback continues to `public/index.php` only when no published static resource matches the request.

The static publisher must prevent path traversal and may only publish inside an approved site-specific output root.

## SEO and canonicalization

A page has one canonical public URL at a time.

Changing permalink style must provide a migration path for existing URLs, including redirects where required. The CMS must not serve `/about/` and `/about.html` as duplicate canonical pages unless explicitly configured for a migration period.

URL generation used by menus, sitemap, canonical tags and internal links must consume the same resolved permalink contract.

## Separation of concerns

The following concerns are deliberately independent:

```text
Template:        templates/pages/default.php
Public path:     /about/
Render mode:     static
Permalink style: clean
Generated file:  /about/index.html
```

or:

```text
Template:        templates/pages/default.php
Public path:     /about.html
Render mode:     static
Permalink style: html
Generated file:  /about.html
```

A template rename must not silently change a public URL. A permalink change must not require changing PHP templates.

## Review checklist

Before merging presentation or routing work, verify:

1. Does any public URL expose `.php`?
2. Is a public URL derived from a template filename?
3. Was Twig/Blade/XSL/XML or another template language introduced?
4. Can the same PHP template render both dynamic and static output?
5. Are clean `/path/` and `/path.html` static permalink modes both supported by the model?
6. Is permalink style selectable independently of render mode?
7. Does static publishing bypass PHP at request time when a generated file exists?
8. Are URL collisions, path traversal and canonical duplication prevented?
9. Can existing URLs be redirected when permalink policy changes?

Any `yes` to questions 1-3 is an architectural defect.
