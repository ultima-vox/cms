<?php

declare(strict_types=1);

use Core\View\SafeHtml;

$properties = is_array($item['properties'] ?? null) ? $item['properties'] : [];
$lead = (string) ($properties['lead'] ?? '');
$image = (string) ($properties['image'] ?? '');
if (!preg_match('#^(https://mechaniki\.ru/|/assets/mechaniki/)#', $image)) {
    $image = '';
}
$kind = 'service';
$label = $kind === 'service' ? 'Услуги автосервиса' : 'Поиск по симптому';
?>
<nav class="wrap breadcrumbs" aria-label="Хлебные крошки">
    <a href="/">Главная</a><span aria-hidden="true">/</span>
    <a href="<?= $kind === 'service' ? '/services' : '/problems' ?>"><?= text($label) ?></a>
    <span aria-hidden="true">/</span><span aria-current="page"><?= text((string) $item['name']) ?></span>
</nav>
<section class="page-hero<?= $kind === 'service' ? '' : ' page-hero--problem' ?>">
    <div class="wrap"><div class="page-hero-grid">
        <div class="page-hero-copy">
            <p class="eyebrow"><?= text($label) ?></p>
            <h1 class="ip-sample-title"><?= text((string) $item['name']) ?></h1>
            <?php if ($lead !== ''): ?><p class="lead"><?= text($lead) ?></p><?php endif; ?>
            <div class="button-row">
                <button class="btn" type="button" data-book>Записаться <span aria-hidden="true">→</span></button>
                <a class="quiet-link" href="tel:+79268150924">Обсудить по телефону</a>
            </div>
        </div>
        <?php if ($image !== ''): ?><figure class="ip-photo"><img src="<?= text($image) ?>" alt="<?= text((string) $item['name']) ?>" width="960" height="720" loading="lazy" decoding="async"></figure><?php endif; ?>
    </div></div>
</section>
<?php if ((string) ($item['content'] ?? '') !== ''): ?>
    <?= html(SafeHtml::fromTrustedStorage((string) $item['content'])) ?>
<?php endif; ?>
