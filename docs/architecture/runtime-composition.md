# Runtime composition contract

Ultima Vox borrows the convenient site-building mental model of HostCMS while keeping a modern modular runtime.

## Architectural invariant

**Core provides execution infrastructure. Business modules provide business domains.**

Core must not import, instantiate, query tables from, or otherwise know concrete business modules such as Menu, Shop, Infosystem, Documents, Forms, Search, Reviews, or future modules.

Allowed direction:

```text
Core <- module
Core extension contract <- module
module A <- declared API of module B (only when explicitly required)
```

Forbidden direction:

```text
Core -> Menu
Core -> Shop
Core -> Infosystem
Core -> Documents
Core -> Forms
```

A useful deletion test is mandatory: physically removing a business module package must not require editing Core. The CMS must still boot, render unrelated pages, open the Core administration area, and run other modules.

If replacing or rewriting a module requires redesigning Core, the boundary is wrong.

## What is intentionally borrowed from HostCMS

Ultima Vox keeps the developer/editor mental model that makes HostCMS practical:

- Structure resolves the public URL and selects page configuration.
- Layouts compose the outer HTML and may be nested.
- Execution is delegated through a page continuation (`$page->execute()`).
- Reusable content entities can be executed in arbitrary places.
- Modules can expose reusable page types and fluent content sources.
- Menus and other presentation sources are selected by stable codes and rendered through dedicated views.

The implementation is intentionally different:

- native PHP templates instead of XML/XSL/Twig;
- typed PHP APIs instead of a global string-based entity factory;
- request-scoped execution instead of global mutable page singletons;
- repositories/query services instead of magic Active Record relations;
- explicit extension registries instead of Core knowing module classes;
- automatic render dependency collection for cache/SSG invalidation.

## Runtime layers

```text
HTTP request
    -> Site resolution
    -> Structure resolution
    -> Page execution preparation
    -> Layout execution chain
    -> terminal page executor
    -> native PHP views
    -> HTML
```

Core owns the generic pipeline and registries only.

A module may register a page executor under a stable code, for example `catalog`, `news`, or another module-defined code. Core stores and resolves that code without importing the module implementation.

## Page execution contract

A page executor receives a request-scoped execution context and returns trusted render output. It may contribute dependencies, assets, cache policy, schema and resource hints through the shared `RenderContext`.

The first Core contract is deliberately small:

```php
$core->pages()->executor('module.page-type', $executor);

$executor = $core->pages()->resolve('module.page-type');
```

The registry is frozen after application boot, exactly like routes, templates and content sources.

Future PRs will build on this stable contract:

1. request-scoped `$page->execute()` continuation;
2. nested layouts;
3. reusable Documents module;
4. module-owned page type metadata/configuration schemas;
5. Menu/Navigation module through generic extension contracts;
6. Infosystem and Shop adapters implemented entirely in their modules.

## `execute()`, `show()` and `view()` convention

The intended public template API uses three distinct concepts:

- `execute()` — execute a page/content entity or continue the page execution chain;
- `show()` — render a configured content source/query;
- `view()` — choose a native PHP presentation view.

Examples of the target ergonomics:

```php
<?= $page->execute() ?>

<?= $documents->get('footer')->execute() ?>

<?= $menus->get('main')->view('default')->show() ?>

<?= $infosystems
    ->get('news')
    ->items()
    ->limit(10)
    ->view('news.list')
    ->show() ?>
```

Only the contracts needed to support these calls belong to Core. `documents`, `menus`, `infosystems`, `shops`, and similar facades belong to their modules.

## Template rule

Layouts and views are ordinary `.php` files. A layout decides where reusable blocks are placed; a Menu module never implies header/footer placement, and a page layout never needs to know whether the terminal page is implemented by Shop, Infosystem, Documents or another module.

The desired outer layout shape is therefore:

```php
<header>
    <?= $menus->get('main')->view('default')->show() ?>
</header>

<main>
    <?= $page->execute() ?>
</main>

<footer>
    <?= $documents->get('footer')->execute() ?>
</footer>
```

The availability of `$menus` or `$documents` is provided by installed modules through template facade registration; Core does not provide or depend on those facades.

## Performance and caching

All nested execution occurs inside one request-scoped `RenderContext`. Every executor/source must register the dependencies it actually uses. The resulting dependency graph is used for page cache and static-page invalidation.

This makes dynamic rendering, page cache and static publishing different delivery modes of the same execution pipeline rather than separate rendering systems.
