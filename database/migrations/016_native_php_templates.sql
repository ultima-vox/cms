UPDATE layouts
SET template_path = 'layouts/main.php',
    updated_at = CURRENT_TIMESTAMP
WHERE template_path IN ('layouts/main.twig', 'layouts/main.html.php');

UPDATE layouts
SET template_path = regexp_replace(template_path, '\.html\.php$', '.php'),
    updated_at = CURRENT_TIMESTAMP
WHERE template_path LIKE 'layouts/%.html.php';
