# Layouts

Layouts are ordinary Twig 3 templates used by site nodes.

Available variables:

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
    <title>{{ node.title }}</title>
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

Keep presentation logic in Twig and business logic in PHP. Avoid complex Twig expressions and macros for ordinary layouts.
