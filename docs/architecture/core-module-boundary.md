# Core / Module Boundary

## Canonical rule

> **Core routes. Modules terminate business domains.**

Ultima Vox CMS follows a strict boundary inspired by hierarchical network design: the core provides transport, routing and shared infrastructure, while business domains terminate inside independently installable modules.

This is a project invariant, not a guideline.

## Core responsibilities

Core may provide only platform-level capabilities that are independent of any specific business domain:

- application bootstrapping;
- HTTP request/response handling;
- routing and route registration;
- authentication and session infrastructure;
- authorization primitives and permission registry;
- site context and site resolution;
- module discovery, compatibility and lifecycle;
- extension APIs and registries;
- cache infrastructure and cache invalidation primitives;
- render context and template runtime;
- event infrastructure;
- migration infrastructure;
- audit infrastructure;
- filesystem/runtime infrastructure;
- health and diagnostics primitives.

Core must not implement knowledge of a concrete business domain.

## Module responsibilities

A module owns the complete vertical slice of its domain, including as applicable:

- domain model;
- controllers and handlers;
- repositories and queries;
- database migrations;
- admin routes and admin UI;
- public routes;
- permissions specific to the module;
- frontend facades and render sources;
- module-owned PHP views/templates;
- module-owned internal admin templates;
- cache dependency tags;
- events originating from the domain;
- assets required by the module;
- install/update/uninstall lifecycle hooks.

Examples of business domains that must terminate in modules:

- infosystems;
- catalog;
- commerce;
- forms;
- search;
- SEO;
- reviews;
- comments;
- imports/integrations.

## Dependency direction

Allowed:

```text
Core <- Module
Core Extension API <- Module
Module A <- Module B, only through declared dependency/API
```

Forbidden:

```text
Core -> Infosystem
Core -> Catalog
Core -> Commerce
Core -> Forms
```

Core may expose generic contracts used by modules, but must not import module classes, query module tables or branch on module-specific fields.

## Generic bindings

When a Core entity needs to be associated with optional module-owned data, Core must store only a generic binding and must not add a module-specific foreign key or column.

Canonical node binding shape:

```text
node_id + module_code + binding_code -> target_key + config
```

Core owns the transport-level binding record and validates only generic concerns such as node scope, code syntax and uniqueness. The module owns:

- the meaning of `binding_code`;
- the meaning and validation of `target_key`;
- lookup of the domain entity represented by `target_key`;
- the administration UI for creating or changing the binding;
- domain-specific cache dependencies and lifecycle cleanup.

For example, the Infosystem module may use:

```text
module_code  = infosystem
binding_code = primary
target_key   = catalog
```

Core must not translate that target into an infosystem ID and must not know that the target represents an infosystem at all.

Stable logical keys are preferred over cross-domain database IDs because a disabled or physically removed module must leave generic Core data readable and harmless.

## Removal test

A module boundary is considered correct only if the physical module package can be removed and the CMS still:

- boots successfully;
- serves ordinary pages that do not depend on that module;
- opens the administration UI;
- runs other installed modules;
- does not reference missing module classes;
- does not require module tables during generic requests;
- does not fail because module routes, permissions or templates are absent.

If removing a module requires editing Core, the boundary is wrong.

## Runtime rule

Core dispatches requests and creates shared runtime context. Modules register themselves during boot through stable typed APIs.

At request time, Core must not inspect a domain to decide how that domain works. It only executes registered handlers and render sources.

For rendering, dependency metadata must be contributed by the module that renders the content. For example, the Infosystem module is responsible for adding `site:<id>:infosystem:<id>` cache dependencies when infosystem content is actually rendered. Core must not infer them by reading module-specific state.

## Persistence rule

Module tables may share the same PostgreSQL database, but ownership remains explicit.

Core migration code must not assume that module tables exist. Module install/update logic owns the schema required by the module.

Cross-module foreign keys should be used only when the dependency is explicit and lifecycle-safe. Prefer stable IDs/contracts and declared dependencies over hidden coupling.

## UI rule

A separately installable module owns its own administration and frontend presentation resources. Installing a module must not require copying its templates into Core directories.

The common administration shell/design system may be provided by Core, but domain screens remain module-owned.

## Commercial rule

This boundary is also required by the product model. Commercial modules can be purchased, installed, enabled, disabled and updated independently of editions.

Editions may bundle modules for convenience, but must not change Core behavior or create edition-specific Core branches.

## Review checklist

Before merging a feature, verify:

1. Does Core import a class from a business module?
2. Does generic Core code query a module-owned table?
3. Does Core check a module-specific field or entity type?
4. Does a module require its files to be copied into a Core directory?
5. Would deleting the module directory break unrelated CMS requests?
6. Does disabling the module leave registered routes/facades/listeners active?
7. Is cache invalidation owned by the code that owns the affected domain?
8. Is every cross-module dependency declared and visible?
9. Was a module-specific foreign key added to a Core table where a generic binding would work?

Any `yes` to questions 1-5 or 9 is an architectural defect unless explicitly documented as a temporary migration exception.

## Short form

The project uses the following permanent architectural principle:

> **Core routes. Modules terminate business domains.**
>
> **If removing a module requires changing Core, the module boundary is wrong.**
