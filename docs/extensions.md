# Extension API

Ultima Vox CMS modules extend the application through stable registries instead of editing `Application.php`, `Router.php` or the frontend renderer.

## Module contract

A module lives under `modules/<code>/` and exposes `module.php`. The manifest returns an implementation of `Core\Extension\ModuleInterface`.

Registration happens only during application boot. After all modules are loaded, the extension core is frozen. Runtime code may read registries and dispatch events, but it cannot add routes, content sources, views, permissions or listeners.

```php
final class ExampleModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $core->permissions()->define(
            'example.manage',
            'Manage example module',
            ['superadmin', 'admin'],
        );

        $core->admin()->navigation(
            'example',
            'Example',
            '/admin/example',
            'example.manage',
            100,
        );

        $core->templates()->view(
            'example.list',
            'example.items',
            'modules/example/templates/list.html.php',
        );
    }
}
```

## Typed APIs

- `runtime()` exposes the application root and the shared PDO connection for backend module code.
- `routes()` registers HTTP routes.
- `templates()` registers frontend facades, context-dependent facade aliases and logical View Templates.
- `content()` registers renderable content source factories.
- `admin()` registers administration navigation metadata.
- `permissions()` registers permission metadata and default roles.
- `events()` registers typed object listeners and dispatches events.
- `extensions()` remains a controlled escape hatch for experimental extension points that do not yet have a typed API.

A stable feature should move from `extensions()` to a dedicated typed API before third-party modules depend on it.

## Permissions

Module permission declarations are not written to the database during HTTP requests. Synchronize them explicitly during install/update/deploy:

```bash
php bin/console migrate
php bin/console extensions:sync
```

Synchronization is additive: it creates/updates declared permissions and adds default role assignments. It never revokes custom role assignments made by an administrator.

## Frontend content

The infosystem module is the reference implementation.

Every PHP layout gets a generic facade:

```php
<?php $news = $infosystems->get('news'); ?>
<?= $news->items()->limit(5)->show() ?>
```

If the current structure node is linked to an infosystem and its code is a valid PHP variable name, a short alias is exposed automatically:

```php
<?= $news->items()->limit(5)->show() ?>
```

Aliases are page-local and are never allowed to overwrite core/page variables or another module facade.

Queries are immutable. Every modifier returns a clone:

```php
<?= $catalog
    ->items()
    ->where('kind', 'bio')
    ->limit(12)
    ->template('infosystem.list')
    ->show() ?>
```

Content is loaded lazily. PHP layouts do not fetch infosystem items unless a render source is actually shown.

## View Templates

A View Template has a stable logical code, a source type and a physical `*.html.php` file shipped by its module. A content source may only render a View Template registered for its own source type.

This keeps database IDs and module internals out of frontend templates and makes module upgrades safe as long as logical view codes remain stable.

## Events

Events are ordinary typed PHP objects:

```php
$core->events()->listen(ItemRendered::class, static function (ItemRendered $event): void {
    // react to the event
});
```

Listener registration is boot-only; dispatch remains available after the registry is frozen.

## Performance rules

- Do not query the database while merely registering a module.
- Prefer lazy source resolution and request-local facade caches.
- Add `RenderContext` dependency tags for every entity that affects rendered output.
- Keep module View Templates free of direct database access.
- Heavy discovery/manifest work will be compiled into a production cache in a later hardening phase.
