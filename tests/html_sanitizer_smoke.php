<?php

declare(strict_types=1);

use Core\Content\HtmlSanitizer;

require dirname(__DIR__) . '/vendor/autoload.php';

$sanitizer = new HtmlSanitizer();

$inline = $sanitizer->inline('🔥 Монтаж <span class="accent md:text-lg">септика</span><script>alert(1)</script><b onclick="x()">сейчас</b>');
if ($inline !== '🔥 Монтаж <span class="accent md:text-lg">септика</span><b>сейчас</b>') {
    throw new RuntimeException('Inline sanitizer output mismatch: ' . $inline);
}

$rich = $sanitizer->rich(
    '<section class="content"><h2>Заголовок 🚗</h2><p style="color:red">Текст <a href="javascript:alert(1)" target="_blank">опасная</a> ' .
    '<a href="https://example.com" target="_blank" rel="nofollow evil">ссылка</a></p><iframe src="https://evil.test"></iframe>' .
    '<img src="/media/test.webp" onerror="alert(1)" alt="Фото" loading="lazy"></section>'
);

foreach (['javascript:', 'style=', 'onerror=', '<iframe', ' evil'] as $forbidden) {
    if (str_contains($rich, $forbidden)) {
        throw new RuntimeException('Rich sanitizer kept forbidden content: ' . $forbidden);
    }
}
if (!str_contains($rich, 'Заголовок 🚗')
    || !str_contains($rich, 'class="content"')
    || !str_contains($rich, 'href="https://example.com"')
    || !str_contains($rich, 'rel="nofollow noopener noreferrer"')
    || !str_contains($rich, 'src="/media/test.webp"')) {
    throw new RuntimeException('Rich sanitizer removed expected safe content: ' . $rich);
}

$encodedAttack = $sanitizer->rich('<a href="jav&#x61;script:alert(1)">x</a><img src="data:text/html,x">');
if (str_contains($encodedAttack, 'href=') || str_contains($encodedAttack, 'src=')) {
    throw new RuntimeException('Encoded unsafe URI was accepted: ' . $encodedAttack);
}

fwrite(STDOUT, "HTML SANITIZER OK\n");
