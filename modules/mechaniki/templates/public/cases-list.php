<?php declare(strict_types=1); ?>
<section class="page-hero page-hero--compact"><div class="wrap"><div class="page-hero-copy"><p class="eyebrow">Истории ремонтов</p><h1>От симптома к результату</h1></div></div></section>
<section class="repair-cases section--spaced"><div class="wrap"><p class="demo-disclosure">Демонстрационные кейсы: автомобили, работы, суммы и сроки условные. Фото — иллюстрации.</p><div class="case-grid demo-case-grid">
<?php foreach ($items as $item): ?>
<?php $p = is_array($item['properties'] ?? null) ? $item['properties'] : []; ?>
<article class="case-card"><img src="/assets/mechaniki/workshop.webp" width="462" height="280" loading="lazy" alt="Автомобиль в ремонтной мастерской">
<div class="case-card__body"><p class="demo-label"><?= !empty($p['is_demo']) ? 'Демо-кейс' : 'Пример ремонта' ?></p>
<h3><?= text((string) $item['name']) ?></h3>
<?php if (!empty($p['year'])): ?><p><?= text((string) $p['year']) ?></p><?php endif; ?>
<dl><dt>Симптом</dt><dd><?= text((string) ($p['symptom'] ?? '')) ?></dd>
<dt>Причина</dt><dd><?= text((string) ($p['cause'] ?? '')) ?></dd>
<dt>Работы</dt><dd><?= text((string) ($p['work'] ?? '')) ?></dd></dl>
<div class="case-card__bottom"><strong><?= text((string) ($p['price_label'] ?? '')) ?></strong><span><?= text((string) ($p['duration'] ?? '')) ?></span></div></div></article>
<?php endforeach; ?>
</div></div></section>
