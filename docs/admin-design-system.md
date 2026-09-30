# Ultima Vox Admin Design System 1.0

Accepted design source: https://www.figma.com/design/CxqJ4su10mwWHsC53qgXzj

## Direction

Ultima Vox Admin is a high-density professional interface: a dark compact global sidebar, light working canvas, neutral typography, one indigo action color, restrained borders and shadows, and tables/forms optimized for repeated editorial work.

The admin UI is an application interface, not a marketing dashboard. Avoid decorative gradients, glassmorphism, oversized KPI cards, excessive whitespace, and module-specific visual languages.

## Stable tokens

The public CSS contract lives in `public/assets/admin.css` and uses `--uv-*` custom properties. First-party and third-party modules should consume these tokens rather than hard-code palette values.

Core semantic tokens include:

- `--uv-canvas` — application workspace background.
- `--uv-surface`, `--uv-surface-2`, `--uv-surface-3` — progressively stronger surfaces.
- `--uv-border`, `--uv-border-strong` — separators and controls.
- `--uv-text`, `--uv-muted`, `--uv-muted-2` — text hierarchy.
- `--uv-accent`, `--uv-accent-soft` — primary action/focus.
- `--uv-success`, `--uv-warning`, `--uv-danger` and their soft variants — semantic states.
- `--uv-radius*`, `--uv-space-*` — geometry and spacing.

## Component contract

Modules should prefer the shared classes before adding module CSS:

- `.admin-page-header`, `.admin-page-title`, `.admin-page-description`
- `.admin-panel`, `.admin-panel--flush`
- `.admin-table`, `.admin-table__primary`, `.admin-table__secondary`
- `.admin-button`, `--secondary`, `--danger`
- `.admin-form`, `.admin-form__grid`, `.admin-field`, `.admin-input`, `.admin-select`, `.admin-textarea`
- `.admin-badge`, `.admin-status`
- `.admin-notice`, `.admin-alert`, `.admin-empty`, `.admin-danger-zone`

Specialized module CSS should define only domain-specific layout such as a tree, media grid, timeline, or product-property editor.

## Navigation icons

Admin navigation registration supports an icon code:

```php
$core->admin()->navigation(
    'reviews',
    'Отзывы',
    '/admin/reviews',
    'reviews.manage',
    40,
    'module',
);
```

The default icon is `module`, so installing a module never requires a Core patch. First-party semantic icons are provided by `public/assets/admin-icons.svg`. A later Assets API may allow module-owned icon packs; modules must not inject arbitrary SVG/HTML into navigation metadata.

## Command palette

`Ctrl/Cmd + K` opens the global palette. Version 1 searches registered admin navigation locally and performs no extra HTTP request. Entity search (products, pages, media, orders, etc.) must be added later through a typed search-provider API rather than hard-coded into the shell.

## Migration policy

1. New admin screens use Design System 1.0 classes immediately.
2. Existing Structure, Layouts, Infosystems and Sites screens keep their routes/controllers and are migrated incrementally.
3. Domain-specific styles are reduced as shared components absorb duplicated CSS.
4. The global admin shell/navigation will be centralized after existing module templates are migrated, so modules no longer duplicate sidebar markup.

This migration is presentation-only unless a PR explicitly documents a behavior or API change. Existing admin routes, permissions, CSRF behavior and module lifecycle remain authoritative.
