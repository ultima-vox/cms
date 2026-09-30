# Frontend layouts

Public site layouts are ordinary HTML files with small PHP insertions (`*.html.php`). There is no XML/XSLT layer and no CMS-specific template language.

Twig is not part of the public frontend contract. It is currently retained only for the internal administration UI and backwards compatibility with early development layouts.

## Runtime model

Packaged layouts live in `templates/layouts` and are treated as application code. Changes made from the administration panel are written to `storage/templates/layouts` as runtime overrides.

Resolution order:

1. `storage/templates/<path>` — site/runtime override;
2. `templates/<path>` — packaged fallback.

This keeps the application tree read-only in production and prevents CMS updates from overwriting site-specific layout edits.

## Available variables

The default public layout receives:

- `$site` — resolved site context;
- `$page` — typed page view model;
- `$node` — current structure record for advanced use;
- module facades registered by enabled modules, for example `$infosystems`.

Prefer `$page` and typed module facades over reading raw `$node` data directly.

## Escaping helpers

Use the small built-in helpers explicitly:

- `text($value)` — escape plain text for HTML;
- `html($safeHtml)` — output trusted/sanitized `SafeHtml`;
- `asset($path)` — build a public asset URL;
- `url($path)` — build and escape an internal URL.

Editorial HTML is sanitized before it reaches the template and is exposed as `SafeHtml`.

## Minimal layout

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

A developer can therefore take an existing HTML layout and replace only the dynamic fragments with normal PHP expressions.

## Module output

Modules expose typed facades instead of XML + XSL transformations. For example, the Infosystems module can render the infosystem linked to the current structure node:

```php
<?php if (isset($infosystems) && ($catalog = $infosystems->linked()) !== null): ?>
    <?= $catalog->items()->show() ?>
<?php endif; ?>
```

The module prepares the data and renders its own PHP view. The layout does not query the database directly.

## Rules

Keep layouts simple:

- HTML markup and presentation conditions are allowed;
- use typed view data and module facades;
- do not execute SQL from a layout;
- do not access the service container from a layout;
- keep business rules in application/module code;
- use reusable PHP views supplied by modules for repeated structured output.

Layouts edited by administrators are trusted executable PHP and therefore require the dedicated `templates.code.edit` permission.
