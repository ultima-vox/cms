# Ultima Vox module contract

Modules extend the CMS through the public Extension API. A module must not patch `core/`, `Application.php`, `BuiltinRoutes.php` or admin templates during installation.

## Package layout

```text
modules/example/
├── module.json
├── module.php
├── autoload.php
├── src/
├── templates/
└── migrations/        # reserved for module migrations
```

`module.json` is declarative and is validated before module PHP bootstrap code is executed:

```json
{
  "code": "example",
  "name": "Example",
  "version": "1.0.0",
  "extension_api": "^1.0",
  "bootstrap": "module.php",
  "migrations": "migrations",
  "default_enabled": false,
  "requires": {
    "infosystem": "^1.0"
  }
}
```

Module versions use semantic `MAJOR.MINOR.PATCH` versions. Version constraints intentionally support a small stable subset: exact versions, `^`, `~`, wildcards, `>`, `>=`, `<`, `<=`, whitespace/comma AND constraints and `||` alternatives. Keeping this parser behind the module loader allows its implementation to change later without changing the manifest contract.

Module codes are stable public identifiers and must not be changed after release. Third-party modules should normally ship with `default_enabled: false`. Bundled first-party modules may opt into `true` for upgrade compatibility.

## Bootstrap

`module.php` returns a `Core\Extension\ModuleInterface` instance or class-string:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

return new Vendor\Example\ExampleModule();
```

The module registers its capabilities during application boot:

```php
final class ExampleModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $core->routes()->get('/example', 'example.index', [$controller, 'index']);
        $core->permissions()->define('example.manage', 'Manage example', ['admin']);
        $core->admin()->navigation(
            'example',
            'Example',
            '/admin/example',
            'example.manage',
        );

        $core->templates()->facade('example', $facadeFactory);
        $core->templates()->view(
            'example.cards',
            'example.items',
            'modules/example/templates/cards.html.php',
        );
    }
}
```

Registries are frozen after boot. Runtime code cannot register or replace routes, facades, permissions or other extension definitions.

## Runtime capabilities

The explicit runtime API supplies shared infrastructure without exposing a generic DI container:

```php
$core->runtime()->database();
$core->runtime()->rootPath();
$core->runtime()->auth();
$core->runtime()->audit();
$core->runtime()->adminRenderer();
```

Prefer module-owned repositories and services. Do not reach into undocumented internal state.

## Dependencies

Enabled modules are loaded in dependency order. The loader rejects:

- missing required modules;
- disabled required modules;
- versions outside the declared constraint;
- incompatible Extension API versions;
- cyclic dependencies.

A structurally valid but incompatible **disabled** module does not prevent the site from booting. Malformed manifests are rejected because their identity and safety cannot be established.

## Lifecycle commands

```bash
php bin/console migrate
php bin/console extensions:sync
php bin/console extensions:list
php bin/console extensions:enable example
php bin/console extensions:disable example
```

`extensions:sync` discovers manifests and updates stored module name/version metadata without changing an existing enable/disable choice. Permission synchronization is additive and does not revoke custom role assignments.

Disabling a module removes its registered routes, template facades, content sources and event listeners on the next request. Database data is not deleted automatically.

## Security model

PHP modules are trusted server-side code. Extension API permissions are capability/documentation boundaries; they are not an OS-level PHP sandbox.

The loader only discovers packages under the dedicated `modules/` directory. Manifest paths are relative, traversal is rejected, and bootstrap paths must resolve inside their own module directory.
