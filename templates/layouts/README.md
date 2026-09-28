# Layouts

Layouts are ordinary Twig 3 templates used by site nodes. There is no XML/XSLT layer and no CMS-specific template language.

## Runtime model

Packaged templates live in `templates/layouts` and are treated as application code. Editor changes are written to `storage/templates/layouts` as runtime overrides. Twig searches runtime overrides first and packaged templates second.

This keeps the application tree read-only in production and avoids conflicts between CMS updates and layout edits made from the admin panel.

## Available variables

- `node` — current node data;
- `content` — trusted editorial HTML stored in the node;
- `items` — published infosystem items attached to the node.

Recommended minimum layout:

```twig
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ node.title ?: node.name }}</title>
    {% if node.meta_description %}
        <meta name="description" content="{{ node.meta_description }}">
    {% endif %}
</head>
<body>
    <main>
        {{ content|raw }}
    </main>
</body>
</html>
```

Keep presentation logic in Twig and business logic in PHP. For ordinary layouts prefer simple HTML, conditions and loops over complex macros or hidden abstractions.
