# Admin UI Foundation

Ultima Vox admin UI is HTML-first and renderer-neutral.

## Runtime contract

- Native PHP views are the target rendering layer.
- CSS owns presentation.
- Vanilla JavaScript ES modules own progressive enhancement.
- Fetch API is transport only; it does not become a second presentation layer.
- PHP remains the source of truth for rendered HTML and business rules.
- No Twig, React, Vue, HTMX, jQuery, or frontend framework runtime is required.

## Compatibility entrypoints

Existing templates may keep loading:

```html
<link rel="stylesheet" href="/assets/admin.css">
<script src="/assets/admin.js" defer></script>
```

`/assets/admin.js` is a compatibility bootstrap that loads the modular admin application.

The future native PHP shell may load the ES module directly:

```html
<script type="module" src="/assets/admin/js/app.js"></script>
```

## CSS contract

Shared admin controls use semantic `--uv-*` tokens from `/assets/admin.css`.

The current production stylesheet remains a single file to avoid an `@import` request waterfall. Domain styles can migrate to shared primitives incrementally.

Compatibility aliases such as `--admin-muted` and `--admin-border` are temporary and exist only for legacy domain styles.

## JavaScript structure

```text
public/assets/admin/js/
├── app.js
├── core/
│   ├── csrf.js
│   ├── dom.js
│   ├── http.js
│   └── storage.js
└── components/
    ├── command-palette.js
    ├── dialog.js
    ├── sidebar.js
    └── site-switcher.js
```

### Core rules

- JS modules must not query repositories or contain domain business logic.
- CSRF tokens are read only from server-rendered DOM.
- CSRF tokens must not be stored in localStorage/sessionStorage.
- Fetch requests default to same-origin credentials.
- Non-2xx responses are failures.
- Behavior is attached through stable `data-*` hooks rather than renderer-specific markup.
- Component initializers should be safe to call again after future partial HTML updates.

## Stable shell hooks

Current behavior depends on these renderer-neutral hooks:

```text
data-admin-shell
data-admin-collapse
data-site-select
data-command-open
data-command-input
data-command-item
data-command-empty
data-dialog-open
data-dialog-close
data-dialog-backdrop-close
```

When Twig is replaced by native PHP, these hooks should be preserved unless a dedicated UI migration changes the contract.

## CSRF sources

The JS helper accepts a server-rendered token from either:

```html
<meta name="csrf-token" content="...">
```

or the existing hidden form field:

```html
<input type="hidden" name="_csrf" value="...">
```

Mutating endpoints remain responsible for server-side CSRF validation.

## Partial updates

Future partial interactions should follow:

```text
User action
-> Fetch API
-> existing admin endpoint/controller
-> server-side validation/business logic
-> HTML fragment or JSON response
-> local DOM update
```

Prefer PHP-rendered HTML fragments for presentation. Use JSON for data-oriented actions where rendering HTML on the client is not required.

## Long-running operations

Use normal Fetch for starting an operation. Server-Sent Events may be introduced later for long-running progress reporting. WebSockets are not part of the foundation contract.
