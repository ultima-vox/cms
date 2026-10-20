<?php declare(strict_types=1); ?>
<nav class="wrap breadcrumbs" aria-label="Хлебные крошки"><a href="/">Главная</a><span aria-hidden="true">/</span><span>Услуги</span></nav>
<section class="page-hero page-hero--compact"><div class="wrap"><div class="page-hero-copy"><p class="eyebrow">Услуги автосервиса</p><h1>Услуги автосервиса в Зеленограде</h1><p class="lead">Диагностика, обслуживание и ремонт — выберите направление.</p></div></div></section>
<section class="main-services section--spaced"><div class="wrap"><div class="section-title"><h2>Направления и виды работ</h2></div><div class="ip-service-grid">
<?php foreach ($items as $item): ?>
<?php $props = is_array($item['properties'] ?? null) ? $item['properties'] : []; ?>
    <a class="ip-service-card" href="<?= url('/services' . (string) $item['path']) ?>">
        <div class="ip-service-body"><div>
            <h3><?= text((string) $item['name']) ?></h3>
            <p><?= text((string) ($props['lead'] ?? '')) ?></p>
        </div><svg class="ui-icon" aria-hidden="true"><use href="/assets/mechaniki/icons.svg#ArrowUpRight"/></svg></div>
    </a>
<?php endforeach; ?>
</div></div></section>
