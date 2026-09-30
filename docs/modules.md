# Module lifecycle

Ultima Vox modules are trusted PHP packages discovered from `modules/*/module.php`.

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

HTTP requests only read module state. They never install, enable, disable, or synchronize modules.

## CLI

```bash
php bin/console extensions:sync
php bin/console extensions:list
php bin/console extensions:enable reviews
php bin/console extensions:disable reviews
```

`modules:list` remains a manifest-only diagnostic command and can run before lifecycle state has been synchronized.

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

## Runtime behavior

Disabled modules do not call their provider `register()` method and therefore contribute no:

- routes;
- template facades/views;
- content sources;
- events;
- admin navigation;
- permission declarations.

Persisted permissions from an earlier enabled state are not destructively deleted when a module is disabled. They are harmless without registered routes and preserve custom RBAC assignments for re-enable. Uninstall/data cleanup is a separate lifecycle operation.

## Future lifecycle

Install/uninstall and module-owned migrations will build on the same persisted state model. Package code installation and database/data removal remain separate explicit operations; disabling a module must never delete its data.
