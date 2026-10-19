<?php declare(strict_types=1); ?>
<nav class="wrap breadcrumbs" aria-label="Хлебные крошки"><a href="/">Главная</a><span aria-hidden="true">/</span><span>Цены</span></nav>
<section class="page-hero page-hero--compact"><div class="wrap"><div class="page-hero-copy"><p class="eyebrow">Стоимость</p><h1>Цены на ремонт и обслуживание</h1><p class="lead">Начальная стоимость работ. Точный расчёт зависит от модели автомобиля и объёма работ.</p></div></div></section>
<section class="price-catalog section--spaced section--compact"><div class="wrap">
<div class="ip-price-controls"><label for="catalog-search">Найти услугу<input id="catalog-search" data-catalog-search type="search" placeholder="Введите услугу" autocomplete="off" aria-controls="catalog-results"></label>
<label for="catalog-category">Категория<select id="catalog-category" data-catalog-category aria-controls="catalog-results">
<option value="">Все категории</option>
<?php $categories = []; foreach ($items as $item) { $p = is_array($item['properties'] ?? null) ? $item['properties'] : []; $category = (string) ($p['category'] ?? ''); if ($category !== '') { $categories[$category] = true; } } ?>
<?php foreach (array_keys($categories) as $category): ?><option><?= text($category) ?></option><?php endforeach; ?>
</select></label></div>
<div id="catalog-results"><div class="ip-prices"><table><caption class="visually-hidden">Начальная стоимость работ</caption><thead><tr><th scope="col">Работа</th><th scope="col">Стоимость</th><th scope="col"><span class="visually-hidden">Запись</span></th></tr></thead><tbody>
<?php foreach ($items as $item): ?>
<?php
    $p = is_array($item['properties'] ?? null) ? $item['properties'] : [];
    $href = (string) ($p['service_href'] ?? '');
    if (preg_match('#^/services(?:/[a-z0-9-]+)*(?:#[a-z0-9-]+)?$#D', $href) !== 1) { $href = '/services'; }
?>
<tr data-catalog-row data-category="<?= text((string) ($p['category'] ?? '')) ?>" data-search="<?= text((string) $item['name']) ?>">
<th scope="row"><a href="<?= url($href) ?>"><?= text((string) $item['name']) ?></a></th>
<td class="ip-price-value" data-label="Стоимость"><?= text((string) ($p['price_label'] ?? 'По запросу')) ?></td>
<td><button class="ip-row-book" type="button" data-book>Записаться</button></td></tr>
<?php endforeach; ?>
</tbody></table></div></div></div></section>
