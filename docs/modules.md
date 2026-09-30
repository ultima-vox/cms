# Module lifecycle

Ultima Vox modules are trusted PHP packages discovered from `modules/*/module.php`.

## Architecture boundary

> **Core routes. Modules terminate business domains.**

A module owns its complete business-domain vertical slice. Core may expose generic infrastructure and stable extension APIs, but it must not import domain-module classes, query module-owned tables during generic requests, or branch on module-specific entities/fields.

The practical acceptance test is simple: removing a module directory must not require editing Core and must not break unrelated CMS pages or other modules.

The complete invariant and review checklist are defined in `docs/architecture/core-module-boundary.md`.

## Manifest

A module returns a PHP array:

```php
return [
    'code' => 'reviews',
    'name' => 'Reviews',
    'version' => '1.0.0',
    'extension_api' => '^1.0',
    'default_enabled' => false,
    'requires' => [
        'core' => '>=0.1.0',
        'infosystem' => '^1.0',
    ],
    'provider' => Vendor\\Reviews\\ReviewsModule::class,
];
```

`extension_api` declares compatibility with the stable PHP Extension API independently from the CMS product version.

Bundled first-party modules use `default_enabled => true` as an upgrade/install bridge. Third-party modules should normally default to disabled and be explicitly enabled after installation.

## Persisted state

`installed_modules` stores package metadata and the administrator's enable/disable choice.

`extensions:sync` updates name/version/API metadata but never overwrites an existing `is_enabled` value. Updating a package therefore cannot silently re-enable a module that was deliberately disabled.

HTTP requests only read module state. They never install, enable, disable, synchronize, or migrate modules.

## CLI

```bash
php bin/console extensions:sync
php bin/console extensions:list
php bin/console extensions:enable reviews
php bin/console extensions:disable reviews
php bin/console modules:migrate reviews
```

`modules:list` remains a manifest-only diagnostic command and can run before lifecycle state has been synchronized.

`modules:migrate <code>` is explicit. Merely copying a module package into `modules/` never changes the database schema.

## Module-owned migrations

New module schema changes live in:

```text
modules/<code>/migrations/*.sql
```

Applied migrations are recorded in `module_schema_migrations` using the tuple `(module_code, migration)` plus a SHA-256 checksum.

Rules:

- migration filenames are immutable after application;
- changing the contents of an applied migration is rejected;
- each migration is applied in its own transaction;
- a PostgreSQL advisory lock serializes migration runs for the same module;
- module migrations are never executed during HTTP boot;
- module migrations are never executed merely because a module directory exists;
- install/update tooling must invoke the migration runner explicitly;
- disabling a module never rolls back or deletes schema/data.

Historical Core migrations that created early Infosystem tables remain immutable for upgrade compatibility. They are a migration-history exception, not the ownership model for new work. Starting with the module migration runtime, all new Infosystem schema changes belong under `modules/infosystem/migrations`.

A later clean-install baseline may compact historical migrations, but existing released migration files and checksums must not be rewritten in place.

## Dependency rules

Only enabled modules enter the runtime graph.

For an enabled module, Core validates:

- Extension API compatibility;
- CMS Core version constraint;
- required module presence;
- required module version;
- required module enabled state;
- dependency cycles.

A disabled module with an incompatible Extension API or missing runtime dependency does not take down the website. It will be rejected when an administrator tries to enable it.

A module cannot be disabled while another enabled module directly depends on it.

Cross-module dependencies must be explicit in the manifest or through a stable typed API. Hidden dependencies through Core internals are prohibited.

## Runtime behavior

Disabled modules do not call their provider `register()` method and therefore contribute no:

- routes;
- template facades/views;
- content sources;
- events;
- admin navigation;
- permission declarations.

Persisted permissions from an earlier enabled state are not destructively deleted when a module is disabled. They are harmless without registered routes and preserve custom RBAC assignments for re-enable. Uninstall/data cleanup is a separate lifecycle operation.

## Package ownership

An independently installable module should own, where applicable:

- domain classes;
- controllers/handlers;
- repositories/queries;
- admin templates and assets;
- public PHP views;
- module-specific routes and permissions;
- cache dependency metadata;
- domain events;
- migrations and install/update/uninstall lifecycle.

Installing a module must not require copying domain files into Core directories.

## Future lifecycle

The next package-lifecycle layer will connect package install/update to the existing explicit operations in a transactional orchestration flow: package verification, compatibility check, backup/maintenance boundary, module migrations, metadata/permission sync, health check, and activation.

Package code installation and database/data removal remain separate explicit operations; disabling a module must never delete its data.
