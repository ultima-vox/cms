UPDATE layouts
SET template_path = 'layouts/main.html.php',
    updated_at = CURRENT_TIMESTAMP
WHERE is_system = TRUE
  AND template_path = 'layouts/main.twig';
