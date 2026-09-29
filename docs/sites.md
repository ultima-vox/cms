# Sites foundation

Ultima Vox resolves public content in a site context before resolving a page.

## Request resolution

Public page resolution follows this order:

```text
Request Host
  -> SiteResolver
  -> SiteContext
  -> NodeRepository(site_id, path)
  -> frontend template
```

`site_domains.host` is globally unique. A registered active domain resolves its site directly.

The host configured by `APP_URL` resolves the built-in `default` site even when it is not duplicated in `site_domains`. This preserves simple single-site installation while avoiding a silent fallback for arbitrary Host headers.

Unknown or malformed hosts do not resolve public content and return 404.

`X-Forwarded-Host` is intentionally ignored. Trusted-proxy handling must be added explicitly rather than accepting forwarding headers from arbitrary clients.

## Template API

PHP frontend templates receive a `Core\Site\SiteContext` instance as `$site`:

```php
<?= text($site->name) ?>
<?= text($site->code) ?>
<?= text($site->host) ?>
```

Site settings are available through:

```php
$site->setting('key', $default)
```

## Database scope

`nodes.path` and `infosystems.code` are unique per site, not globally. This allows different sites to have the same URL tree and logical content codes.

A database trigger rejects a node whose parent or linked infosystem belongs to another site.

Infosystem groups/items remain children of an infosystem and inherit site scope from it; they do not duplicate `site_id` in this schema version.

Layouts remain a shared global template library. A site-specific theme/layout assignment can be added later without duplicating template records by default.

## Upgrade compatibility

Migration `008_sites.sql` creates site `id=1`, code `default`, and assigns all existing nodes and infosystems to it. Existing single-site URLs therefore remain unchanged.

During the first Sites foundation step, the legacy admin CRUD is explicitly scoped to site `1`. Additional sites are not exposed through admin UI yet. The next Sites PR introduces an authenticated admin SiteContext/site selector and removes that temporary default-site binding.

## Cache and delivery rule

Every future cache/page/static key that depends on content must include site identity. Render dependencies already include site-aware tags such as:

```text
site:2:infosystem:15
site:2:infosystem_item:91
```

This rule is required for Delivery, static HTML, Media and CDN work that follows Sites foundation.
