<?php declare(strict_types=1); ?>
<section class="section" id="services"><div class="wrap"><div class="section-heading"><div><p class="eyebrow">Услуги</p><h2>Основные направления</h2></div><a class="quiet-link" href="/services">Все услуги →</a></div><div class="services-grid">
<?php $shown = 0; foreach ($items as $item): if ($item['group_id'] !== null || $shown >= 6) { continue; } $shown++; ?>
<?php $p = is_array($item['properties'] ?? null) ? $item['properties'] : []; ?>
<a class="service-card" href="<?= url('/services' . (string) $item['path']) ?>"><div class="service-card__body"><h3><?= text((string) $item['name']) ?></h3><p><?= text((string) ($p['lead'] ?? '')) ?></p></div><span aria-hidden="true">→</span></a>
<?php endforeach; ?>
</div></div></section>
