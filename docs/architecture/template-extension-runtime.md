# Template & Extension Runtime v1

## Goals

Ultima Vox templates are ordinary HTML documents with small PHP insertions. Frontend developers must not need to understand repositories, DI, PostgreSQL, JSONB, routing internals, or the module bootstrap process.

The core exposes a stable extension surface so first-party and third-party modules can add behavior without patching `Application.php`, `Router.php`, or template internals.

## Template rules

New frontend layouts use `*.html.php`. Twig remains temporarily available only for admin and legacy frontend templates during migration.

Core helpers are intentionally small:

- `text()` — HTML-escapes ordinary text while preserving valid UTF-8, including emoji;
- `html()` — outputs only a `SafeHtml` value;
- `asset()` — creates a local asset URL;
- `url()` — creates a local URL and optionally an absolute URL.

`image()` belongs to the future Media module and will be added on top of the same runtime.

A frontend template may be a complete HTML page. Partials and component helpers are optional, never mandatory.

## Render semantics

A module may expose a simple template facade such as `$shop`, `$news`, or `$reviews` through `TemplatesApi::facade()`.

`show()` renders only the current render source. `add()` creates an explicit composition. No unrelated visual source is injected implicitly.

Conceptually:

```php
<?= $shop->content()->show() ?>
```

renders only shop content, while:

```php
<?= $shop
    ->content()
    ->add($reviews->forCurrent()->template('reviews.product')->limit(5))
    ->show() ?>
```

renders the shop source plus the explicitly added reviews source.

Multiple independent `show()` calls in one HTML template are valid. They must share a request render context so cache dependencies, assets, image presets, schema, and future preload metadata can be aggregated.

## View templates

View templates use stable logical codes (`reviews.product`, `news.cards`) rather than database IDs. A registered view declares its source type and physical `*.html.php` path. PHP, system, and future visual/WYSIWYG renderers must converge on the same ViewDefinition contract.

Views format prepared data; they do not query the database or external APIs.

## Extension runtime

Modules implement `Core\\Extension\\ModuleInterface` and register during application boot:

```php
final class ReviewsModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $core->routes()->get('/reviews', 'reviews.index', [$this, 'index']);
        $core->templates()->facade('reviews', fn ($context) => new ReviewsFacade($context));
        $core->templates()->view('reviews.product', 'reviews.collection', 'reviews/product.html.php');
    }
}
```

The runtime is frozen before request dispatch. Late registration fails. This keeps request behavior deterministic and allows a compiled runtime cache later.

Known extension categories get typed APIs (`routes()`, `templates()`). `extensions()` is the controlled escape hatch for capabilities we have not designed yet; mature extension points should graduate to dedicated typed APIs without breaking existing modules.

Module PHP is trusted code. The runtime API controls architecture and compatibility, not OS-level sandboxing.

## Migration

The system layout moves from `layouts/main.twig` to `layouts/main.html.php`. Existing custom Twig layouts continue to render during the transition. New layouts are created as `*.html.php` and validated with the PHP parser before an atomic write.

Stored HTML is currently wrapped as trusted legacy content. Sanitizer-on-write remains required before low-trust editors receive broad HTML editing access.
