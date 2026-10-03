# Documents module

Reusable, site-scoped, versioned HTML documents.

Frontend usage:

```php
<?= $documents->get('footer')->execute() ?>
```

Structure page type:

```text
documents.page
```

with configuration:

```json
{"document":"about"}
```

Documents owns its schema, repository, facade and page executor. Core remains unaware of the module domain.
