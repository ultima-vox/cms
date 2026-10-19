<?php declare(strict_types=1); ?>
<section class="section section--soft" id="prices"><div class="wrap price-layout"><div class="price-intro"><p class="eyebrow">Стоимость</p><h2>Популярные работы. Цена от</h2><p>Стоимость зависит от автомобиля и согласованного объёма работ.</p><a class="quiet-link" href="/prices">Полный прайс →</a></div><div class="price-list">
<?php foreach (array_slice($items, 0, 5) as $item): ?>
<?php $p = is_array($item['properties'] ?? null) ? $item['properties'] : []; ?>
<div class="price-list__row"><span><?= text((string) $item['name']) ?></span><strong><?= text(\UltimaVox\Modules\Mechaniki\PriceLabel::fromProperties($p)) ?></strong></div>
<?php endforeach; ?>
</div></div></section>
