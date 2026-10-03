# Documents frontend runtime

The Documents module owns reusable versioned HTML documents. Core does not import Documents classes or query Documents tables.

## Template facade

Native PHP templates may execute a published document by stable site-scoped code:

```php
<?= $documents->get('footer')->execute() ?>
```

The facade resolves the document for the current `SiteContext`, then renders its current published version into the request `RenderContext`.

## Page type

Structure nodes may use the module-owned page executor:

```text
page_type: documents.page
page_config:
  document: about
```

The executor resolves the document for the current site and renders the same published version through the same `DocumentRenderer` used by the template facade.

## Dependency metadata

Document execution records dependencies for the document, its site-scoped code and the exact published version. Delivery/cache layers can therefore invalidate only pages that actually consumed the changed document.

## Boundary

Documents remains a business module. Core only exposes generic template-facade, page-executor and render-context extension contracts. Removing or replacing the Documents module must not require Core changes.
