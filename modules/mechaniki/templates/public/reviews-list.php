<?php declare(strict_types=1); ?>
<section class="page-hero page-hero--compact"><div class="wrap"><div class="page-hero-copy"><p class="eyebrow">Отзывы клиентов</p><h1>Отзывы об автосервисе</h1></div></div></section>
<section class="reviews section--spaced"><div class="wrap"><div class="verified-reviews">
<?php foreach ($items as $item): ?>
<?php
    $p = is_array($item['properties'] ?? null) ? $item['properties'] : [];
    $source = (string) ($p['source_url'] ?? '');
    if (!str_starts_with($source, 'https://yandex.ru/maps/')) { $source = ''; }
?>
<figure class="verified-review"><span class="review-stars" aria-label="Оценка <?= (int) ($p['rating'] ?? 0) ?> из 5">★★★★★</span>
<blockquote><?= text((string) ($p['text'] ?? '')) ?></blockquote><figcaption><strong><?= text((string) $item['name']) ?></strong>
<?php if (!empty($p['date'])): ?><time datetime="<?= text((string) $p['date']) ?>"><?= text((string) $p['date']) ?></time><?php endif; ?>
<?php if ($source !== ''): ?><a href="<?= text($source) ?>" rel="noopener noreferrer" target="_blank">Яндекс Карты</a><?php endif; ?>
</figcaption></figure>
<?php endforeach; ?>
</div></div></section>
