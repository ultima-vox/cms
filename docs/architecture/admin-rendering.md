# Admin rendering contract

Ultima Vox CMS administration uses native PHP rendering and progressive enhancement only.

## Canonical stack

- PHP 8.4+ controllers, services and repositories
- native PHP view files (`*.php`)
- HTML-first server-side rendering
- CSS for presentation
- vanilla JavaScript ES modules for interaction
- Fetch API for asynchronous requests
- PHP-rendered HTML fragments for partial UI updates where practical
- JSON only for data-oriented operations
- Server-Sent Events only for long-running progress/status streams when needed

Twig, Blade, XSL/XML, HTMX, React, Vue and custom template languages are not part of the admin rendering contract.

## Rendering flow

Normal page requests:

```text
Request -> Controller -> Service/Repository -> ViewModel/data -> PHP View -> HTML
```

Progressively enhanced interactions:

```text
Browser fetch() -> Admin endpoint -> Controller -> Service -> HTML fragment or JSON -> DOM update
```

The server remains the source of truth. JavaScript enhances interaction and must not become a second independent presentation layer.

## Operational principle

Primary CRUD flows must remain usable with normal form submissions and HTTP redirects. JavaScript should improve search, filtering, sorting, inline state changes, dialogs, uploads and similar local interactions without turning the administration area into an SPA.

## Security

- mutating requests require CSRF validation;
- templates receive prepared data and never query the database;
- output is escaped by default through explicit helpers;
- arbitrary request-controlled template paths are forbidden;
- frontend code contains no secrets or server credentials.
