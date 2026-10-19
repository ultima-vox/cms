<?php declare(strict_types=1); ?>
<section class="section reviews" id="reviews"><div class="wrap"><div class="section-heading"><div><p class="eyebrow">Впечатления клиентов</p><h2>Отзывы о «Механиках»</h2></div><a class="quiet-link" href="/reviews">Все отзывы →</a></div><div class="verified-reviews">
<?php foreach (array_slice($items, 0, 3) as $item): ?>
<?php $p = is_array($item['properties'] ?? null) ? $item['properties'] : []; ?>
<figure class="verified-review"><span class="review-stars" aria-label="Оценка <?= (int) ($p['rating'] ?? 0) ?> из 5">★★★★★</span><blockquote><?= text((string) ($p['text'] ?? '')) ?></blockquote><figcaption><strong><?= text((string) $item['name']) ?></strong></figcaption></figure>
<?php endforeach; ?>
</div></div></section>
