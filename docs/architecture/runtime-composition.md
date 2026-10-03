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
    -> Page type resolution and configuration validation
    -> Layout execution chain
    -> terminal page executor
    -> native PHP views
    -> HTML
```

Core owns the generic pipeline and registries only.

A module registers a page type under a stable code such as `documents.page`, `infosystem.list` or a future `shop.catalog`. Core stores and resolves that code without importing the module implementation.

## Page type contract

A page type is more than an executor. It is a module-owned declaration containing:

- stable `code`;
- human-readable `name` and `description`;
- terminal `PageExecutorInterface`;
- JSON-Schema-compatible configuration metadata for admin/API consumers;
- optional `PageConfigurationValidatorInterface` for authoritative normalization and validation;
- deterministic sorting metadata;
- optional default-page marker.

Example module registration:

```php
$core->pages()->type(new PageTypeDefinition(
    code: 'module.page-type',
    name: 'Module page',
    executor: $executor,
    configurationSchema: [
        'type' => 'object',
        'properties' => [
            'limit' => ['type' => 'integer', 'minimum' => 1],
        ],
        'additionalProperties' => false,
    ],
    configurationValidator: $validator,
));
```

The JSON schema is presentation/discovery metadata. Runtime correctness never depends on a JavaScript form or schema renderer: the module validator is authoritative and executes on the server before the page executor.

Only one registered page type may declare itself as the default. Core does not choose that module and does not contain a hard-coded Documents/Shop/Infosystem default. If no installed module declares a default, the registry returns no default and a caller must make the page type explicit.

The registry is frozen after application boot, exactly like routes, templates and content sources.

`PagesApi::executor()` remains a transitional shorthand for executor-only extensions; first-party modules use the full `PageTypeDefinition` contract.

## Page execution contract

A page executor receives a request-scoped execution context and returns trusted render output. It may contribute dependencies, assets, cache policy, schema and resource hints through the shared `RenderContext`.

Production resolution is:

```text
node.page_type
    -> PagesApi::definition()
    -> module configuration validator
    -> nested layout stages
    -> definition executor
```

`NodeController` never branches on a module code and never imports a module class.

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

## Remaining transition work

The next Structure integration must consume `PagesApi::definitions()` and `PagesApi::default()` generically. It must not hard-code `documents.page` when creating a normal page. Module-specific provisioning of page resources belongs behind an extension contract, not in `StructureController`.

After Structure no longer relies on the legacy Core content field, `core.content`, `CoreContentPageExecutor` and `nodes.content` can be removed in a separate cleanup migration.
