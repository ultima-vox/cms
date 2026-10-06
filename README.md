# Ultima Vox CMS

Lightweight CMS built around a simple HostCMS-inspired content model: nodes, layouts and independently installable content modules, without XML/XSLT or EAV queries.

The public frontend and administration UI use native HTML + PHP templates; no separate template language or legacy renderer is supported.

## Requirements

- PHP 8.4+
- PostgreSQL 16+
- Nginx
- Composer 2
- PHP extensions: `pdo`, `pdo_pgsql`, `json`, `dom`, `mbstring`

## Install

```bash
git clone https://github.com/ultima-vox/cms.git
cd cms
composer install --no-dev --classmap-authoritative
cp .env.example .env
```

Configure `.env`, then run:

```bash
php bin/console migrate
php bin/console health
php bin/console templates:lint
```

Create the first administrator without putting the password into shell history:

```bash
read -rsp 'Admin password: ' CMS_ADMIN_PASSWORD; echo
export CMS_ADMIN_PASSWORD
php bin/console user:create admin@example.com 'Administrator'
unset CMS_ADMIN_PASSWORD
```

Point Nginx document root to `public/`. A reference configuration is available at `deploy/nginx.conf.example`.

The PHP-FPM user must be able to write to:

- `storage/cache`;
- `storage/logs`;
- `storage/templates/layouts`.

The rest of the application tree can remain read-only.

## Architecture principle

> **Core routes. Modules terminate business domains.**

Core provides platform infrastructure: HTTP routing, security primitives, site context, module lifecycle, extension APIs, rendering/cache infrastructure, events, migrations, audit and diagnostics. Concrete business domains such as infosystems, catalog, commerce, forms or reviews belong entirely to independently installable modules.

If removing a module requires changing Core, the module boundary is wrong.

The canonical rules and review checklist are documented in `docs/architecture/core-module-boundary.md`.

## Core model

Core-owned platform data and resources include:

- `nodes` — hierarchical site structure and pages;
- `layouts` — page layout metadata;
- packaged public layout files — `templates/layouts/*.html.php`;
- editor-created layout overrides — `storage/templates/layouts/*.html.php`;
- `users`, `roles`, `permissions` — administration access control;
- `audit_log` — security and change audit storage;
- module registry/lifecycle state and shared delivery infrastructure.

Domain data is module-owned. For example, the Infosystem module currently owns the `infosystems`, `infosystem_groups` and `infosystem_items` domain even while historical migrations are being moved toward module-owned lifecycle management.

## Frontend layout model

A public layout is a normal PHP/HTML file. The default system layout is `templates/layouts/main.html.php`.

Example:

```php
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= text($page->title) ?></title>
</head>
<body>
    <main>
        <?= html($page->content) ?>
    </main>
</body>
</html>
```

There is no XML/XSLT transform and no CMS-specific frontend template language. Dynamic modules expose typed PHP facades and PHP views. For example, an enabled Infosystems module may be inserted directly from the layout:

```php
<?php if (isset($infosystems) && ($catalog = $infosystems->linked()) !== null): ?>
    <?= $catalog->items()->show() ?>
<?php endif; ?>
```

Layout resolution checks `storage/templates` first and falls back to packaged `templates`, so editing a packaged layout in the administration panel creates a runtime override without changing the original file.

The frontend helpers are deliberately small and explicit: `text()` for escaped text, `html()` for trusted `SafeHtml`, `asset()` for assets and `url()` for internal URLs.

The administration UI uses `AdminPhpRenderer`, created through `AdminPhpRendererFactory`. Public and admin templates use native PHP; no legacy template engine is available.

## Infosystem model

Infosystems are reusable content stores for catalogs, news, services and similar structured data. Groups and items are stored separately; there is no `is_group` polymorphic row model in the production schema.

Standard fields such as name, slug, publication state, sorting, content and timestamps are regular PostgreSQL columns. Additional field definitions are stored once in `infosystems.field_schema JSONB`; item values are stored in `infosystem_items.properties JSONB`.

Public equality filters use PostgreSQL containment queries:

```sql
properties @> '{"kind":"bio","capacity":5}'::jsonb
```

The `idx_items_properties_gin` GIN index supports these containment filters without EAV joins. Admin forms are generated from the field schema, so content editors never edit raw JSON.

Migration filenames are immutable after merge because `schema_migrations` records the complete filename. Historical numbering is therefore preserved rather than renamed in place.

## Modules

CMS capabilities are extended through independently installable modules. The core exposes typed extension APIs for routes, permissions, admin navigation, content sources, frontend facades, cache invalidation and site context.

Each business module owns its complete vertical slice: domain code, repositories, routes, permissions, admin UI, public views, cache dependencies and eventually its migrations/install lifecycle. Core must not import module classes or query module-owned tables during generic requests.

Commercial packaging must not force functionality into editions: modules can be licensed, purchased, installed, enabled and updated independently. Editions may exist only as convenient bundles of modules.

Project-specific modules can use the same extension API without becoming part of the CMS core.

## Commands

```bash
php bin/console migrate
php bin/console health
php bin/console templates:lint
php bin/console modules:list
php bin/console extensions:sync
php bin/console extensions:list
php bin/console user:create <email> <display-name>
```

`templates:lint` validates native PHP templates in Core and enabled modules.

## Runtime

- `/health` — health endpoint;
- `/admin/login` — administration login;
- `/admin` — protected administration dashboard;
- `/admin/structure` — site structure management;
- `/admin/layouts` — PHP/HTML public layout management;
- `/admin/infosystems` — provided only when the Infosystem module is installed and enabled;
- all remaining URLs are resolved through registered routes and the site structure fallback.

## Backup

Back up PostgreSQL and the `storage/` directory. Runtime layout overrides are application data and must be included in backups.

## Security baseline

The core enables CSRF tokens for state-changing admin requests, strict cookie sessions, login throttling, RBAC checks, HTML sanitization at write boundaries and standard browser security headers. Editing executable PHP layouts requires a separate trusted permission (`templates.code.edit`). Production deployments should terminate TLS and keep `APP_DEBUG=false`.
