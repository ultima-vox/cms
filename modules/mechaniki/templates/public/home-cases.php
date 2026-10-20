<?php declare(strict_types=1); ?>
<section class="section" id="works"><div class="wrap"><div class="section-heading"><h2>Примеры ремонтов</h2><a class="quiet-link" href="/works">Посмотреть все →</a></div><p class="demo-disclosure">Демонстрационные кейсы: данные условные, фотографии иллюстративные.</p><div class="case-grid">
<?php foreach (array_slice($items, 0, 3) as $item): ?>
<?php $p = is_array($item['properties'] ?? null) ? $item['properties'] : []; ?>
<article class="case-card"><div class="case-card__body"><p class="demo-label"><?= !empty($p['is_demo']) ? 'Демо-кейс' : 'Работа' ?></p><h3><?= text((string) $item['name']) ?></h3><p><?= text((string) ($p['symptom'] ?? '')) ?></p><div class="case-card__bottom"><strong><?= text((string) ($p['price_label'] ?? '')) ?></strong><span><?= text((string) ($p['duration'] ?? '')) ?></span></div></div></article>
<?php endforeach; ?>
</div></div></section>
