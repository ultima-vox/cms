# Admin UI Foundation

Ultima Vox Admin is HTML-first and renderer-neutral. The target runtime is native PHP templates, CSS and vanilla JavaScript. The UI layer must not depend on Twig, a SPA framework, Node.js or a frontend build process at runtime.

## Runtime contract

- Server rendering remains authoritative for full admin pages and forms.
- `/assets/admin.css` is the single shared production stylesheet entrypoint.
- `/assets/admin.js` is the compatibility bootstrap. It loads `/assets/admin/js/app.js` as an ES module, so the current shell can keep a classic deferred script while the future PHP shell may switch directly to `type="module"`.
- Domain screens may load their own CSS/JS after the shared assets, but must reuse shared `admin-*` primitives before adding domain-specific controls.
- JavaScript progressively enhances server-rendered HTML. Core form workflows must not require JavaScript unless the feature itself is inherently interactive.

## CSS contract

Shared semantic tokens use the `--uv-*` namespace. Domain styles must consume semantic tokens rather than copy palette values where practical.

Core primitives currently cover:

- application shell, navigation and topbar;
- page headers, panels, metrics and toolbars;
- buttons and icon buttons;
- inputs, selects, textareas, checkbox/radio rows and switches;
- tables and status rows;
- badges, notices, empty states and danger zones;
- dialogs and the command palette;
- focus-visible, disabled, invalid, busy and reduced-motion states.

`--admin-muted` and `--admin-border` are temporary compatibility aliases for older domain styles. New code must use `--uv-muted` and `--uv-border`.

Known migration item: `admin-textarea--small` currently belongs to `infosystems.css` although Structure and Layouts also use the modifier. Move it into the shared control contract when domain CSS is consolidated; do not duplicate the rule across modules.

## JavaScript contract

Shared modules live under `public/assets/admin/js`.

### Core

- `core/storage.js` — safe optional UI preferences in localStorage.
- `core/dom.js` — DOM query/delegation and busy-state helpers.
- `core/csrf.js` — reads CSRF only from server-rendered DOM and appends it to FormData when needed.
- `core/http.js` — same-origin Fetch wrapper with timeout, response parsing and typed HTTP errors.

The HTTP core defines transport behavior only. It must not contain business routes or module-specific payloads.

### Components

- `components/sidebar.js` — persisted sidebar collapse state.
- `components/site-switcher.js` — progressive enhancement of the existing site selector form.
- `components/command-palette.js` — local navigation command palette.
- `components/dialog.js` — generic native `<dialog>` open/close behavior.

Stable DOM hooks are `data-*` attributes. JavaScript behavior must not depend on visual CSS selectors when a dedicated behavior hook is appropriate.

Generic dialog hooks:

```html
<button type="button" data-dialog-open="example-dialog">Open</button>

<dialog id="example-dialog" class="admin-dialog" data-dialog-backdrop-close>
    <button type="button" data-dialog-close>Close</button>
</dialog>
```

## Async interaction rules

Use native `fetch()` only when an operation benefits from an in-place update. Do not convert ordinary create/edit forms to client-side applications without a concrete UX reason.

Preferred response forms:

1. normal POST -> validation/save -> `303 See Other` for regular forms;
2. server-rendered HTML fragment for partial list/editor updates where PHP remains responsible for markup;
3. JSON for state-oriented operations where returning markup is not appropriate;
4. SSE only for long-running server-side operations that need progress updates.

Do not invent parallel frontend business logic. Backend permissions, validation, CSRF, audit and persistence remain authoritative.

## Security invariants

- Never place CSRF tokens, credentials, API keys or other secrets in localStorage.
- Mutating async operations must use the same backend CSRF and permission checks as normal forms.
- Fetch defaults to `credentials: same-origin`.
- HTTP failures must remain failures; callers decide how to present them and must not show false success.
- Server output remains responsible for escaping and validation. Client validation is additional UX, not a security boundary.

## Renderer migration boundary

The native-PHP template migration owns renderer classes, application composition and conversion of Twig files. The UI foundation owns shared CSS/JS and stable DOM behavior contracts.

A PHP shell should preserve the existing behavior hooks where possible:

- `data-admin-collapse`;
- `data-site-select`;
- `data-command-open`;
- `data-command-input`;
- `data-command-item`;
- `data-command-empty`.

This keeps renderer migration independent from interaction implementation and avoids a second UI rewrite.
