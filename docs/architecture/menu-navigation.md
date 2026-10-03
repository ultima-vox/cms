# Menu and navigation source contract

The Menu domain is a module. Core does not own menu tables, menu rendering, navigation trees or business-module navigation adapters.

## Public template API

A layout chooses where a menu is rendered:

```php
<?= $menus->get('main')->view('default')->show() ?>
```

`main` is a stable site-scoped menu code. `default` is a native PHP view. Menu has no header/footer/sidebar semantics.

## Menu tree

A menu consists of hierarchical module-owned `menu_items`.

Two item kinds exist:

- `link` — a manually configured label and URL;
- `source` — an insertion point that expands a registered dynamic navigation source.

A source item does not render its own wrapper node. Its returned nodes are spliced into the source item's position in the surrounding menu tree. Therefore a manual item such as `Catalog` may contain a source item whose provider returns product groups.

Source items are leaf placeholders and cannot contain ordinary child menu items.

## Navigation source contract

The Menu module owns `NavigationSourceInterface`, `NavigationSourceContext` and `NavigationNode`.

Providers register through Core's already generic extension registry:

```php
$core->extensions()->register(
    NavigationSourceInterface::EXTENSION_POINT,
    'vendor.source',
    $provider,
);
```

Menu resolves only the configured source code and validates that the registered object implements its contract. It never branches on `shop`, `infosystem` or another module code.

A provider receives the current site, node, menu identity and shared `RenderContext`. It returns a typed `NavigationNode` tree and records the domain dependencies it actually used.

## Cross-module integrations

Business modules remain independently replaceable.

Forbidden:

```text
Menu -> Shop implementation
Menu -> Infosystem implementation
Shop -> Menu implementation internals
Infosystem -> Menu implementation internals
```

For integrations between two optional business modules, use a thin bridge/adaptor module that declares both dependencies and registers the provider. Examples:

```text
menu-shop
  requires: menu, shop

menu-infosystem
  requires: menu, infosystem
```

This keeps Menu reusable without Shop and keeps Shop reusable without Menu. Replacing either business module only requires replacing its small bridge.

Structure navigation is different: Structure is a Core domain, so the Menu module may provide a Structure navigation source using Core's public Structure data contract without creating a Core -> Menu dependency.

## Performance and invalidation

Menu rendering performs one site-scoped menu lookup and one flat item query, then assembles the hierarchy in memory. Dynamic sources are invoked only for active source items.

The Menu source registers aggregate and item dependencies such as:

```text
menu:17
site:2:menu:17
site:2:menu:main
menu_item:41
```

Each dynamic provider adds its own domain dependencies to the same request-scoped `RenderContext`. Page cache and static publishing therefore invalidate according to the complete composed dependency graph.
