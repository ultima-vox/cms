<?php declare(strict_types=1); ?>
<nav class="wrap breadcrumbs" aria-label="Хлебные крошки"><a href="/">Главная</a><span aria-hidden="true">/</span><span>Неисправности</span></nav>
<section class="page-hero page-hero--compact"><div class="wrap"><div class="page-hero-copy"><p class="eyebrow">Неисправности</p><h1>Что беспокоит автомобиль?</h1><p class="lead">Выберите знакомый симптом. Название неисправной детали знать необязательно.</p></div></div></section>
<section class="symptoms section--spaced section--soft"><div class="wrap"><div class="ip-symptom-grid">
<?php foreach ($items as $item): ?>
<?php $props = is_array($item['properties'] ?? null) ? $item['properties'] : []; ?>
    <a class="ip-symptom" href="<?= url('/problems' . (string) $item['path']) ?>">
        <svg class="ui-icon" aria-hidden="true"><use href="/assets/mechaniki/icons.svg#Wrench"/></svg>
        <div><h3><?= text((string) $item['name']) ?></h3><p><?= text((string) ($props['lead'] ?? '')) ?></p></div>
        <svg class="ui-icon" aria-hidden="true"><use href="/assets/mechaniki/icons.svg#ArrowRight"/></svg>
    </a>
<?php endforeach; ?>
</div></div></section>
