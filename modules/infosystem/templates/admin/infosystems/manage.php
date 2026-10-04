<?php

declare(strict_types=1);

$adminActive = 'infosystems';
$infosystemId = (int) ($infosystem['id'] ?? 0);
$itemTotal = isset($item_total) ? (int) $item_total : (int) ($infosystem['item_count'] ?? count($items ?? []));
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => (string) ($infosystem['name'] ?? 'Инфосистема'),
    'admin_styles' => ['/assets/infosystems.css'],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow"><?= text($infosystem['code'] ?? '') ?></p><h1 class="admin-header__title"><?= text($infosystem['name'] ?? '') ?></h1><?php if (!empty($infosystem['description'])): ?><p class="admin-page-header__description"><?= text($infosystem['description']) ?></p><?php endif; ?></div><div class="admin-actions"><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>/edit">Настройки</a><a class="admin-button" href="/admin/infosystems">К списку</a></div></header>
    <?php if (!empty($created)): ?><div class="admin-notice">Инфосистема создана.</div><?php endif; ?>
    <?php if (!empty($saved)): ?><div class="admin-notice">Изменения сохранены.</div><?php endif; ?>
    <?php if (!empty($deleted)): ?><div class="admin-notice">Объект удалён.</div><?php endif; ?>

    <div class="content-stats"><div class="content-stat"><strong><?= text($infosystem['group_count'] ?? 0) ?></strong><span>групп</span></div><div class="content-stat"><strong><?= text($infosystem['item_count'] ?? 0) ?></strong><span>элементов</span></div><div class="content-stat"><strong><?= text(count(is_array($infosystem['field_schema'] ?? null) ? $infosystem['field_schema'] : [])) ?></strong><span>доп. полей</span></div></div>

    <section class="admin-panel admin-panel--flush content-section">
        <div class="content-section__header"><div><h2 class="admin-panel__title">Группы</h2><p class="admin-field__hint">Иерархия внутри инфосистемы. URL группы рассчитывается автоматически из slug и родителя.</p></div><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>/groups/create">Новая группа</a></div>
        <?php if (($groups ?? []) === []): ?><div class="admin-empty">Групп нет. Элементы можно хранить и в корне инфосистемы.</div><?php else: ?>
            <div class="content-table__head content-table__head--groups"><span>Группа</span><span>Путь</span><span>Элементы</span><span></span></div>
            <?php foreach ($groups as $group): ?><div class="content-row content-row--groups"><div class="content-row__tree" style="--tree-depth:<?= text($group['depth'] ?? 0) ?>"><strong><?= text($group['name'] ?? '') ?></strong><?php if (empty($group['is_active'])): ?><span class="admin-badge">выкл.</span><?php endif; ?></div><code><?= text($group['path'] ?? '') ?></code><span><?= text($group['item_count'] ?? 0) ?></span><a class="admin-link" href="/admin/infosystems/<?= text($infosystemId) ?>/groups/<?= text($group['id'] ?? '') ?>/edit">Изменить</a></div><?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="admin-panel admin-panel--flush content-section">
        <div class="content-section__header"><div><h2 class="admin-panel__title">Элементы</h2><p class="admin-field__hint">На обзоре показаны первые 25 из <?= text($itemTotal) ?> элементов. Поиск, фильтры и пагинация доступны в полном списке.</p></div><div class="admin-actions"><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>/items">Все элементы</a><a class="admin-button admin-button--primary" href="/admin/infosystems/<?= text($infosystemId) ?>/items/create">Новый элемент</a></div></div>
        <?php if (($items ?? []) === []): ?><div class="admin-empty">Элементов пока нет.</div><?php else: ?>
            <div class="content-table__head content-table__head--items"><span>Название</span><span>Группа</span><span>Статус</span><span>Путь</span><span></span></div>
            <?php foreach ($items as $item): ?><div class="content-row content-row--items"><strong><?= text($item['name'] ?? '') ?></strong><span class="content-row__muted"><?= text(($item['group_name'] ?? '') ?: 'Корень') ?></span><span class="admin-status admin-status--<?= text($item['status'] ?? '') ?>"><?= text($item['status'] ?? '') ?></span><code><?= text($item['path'] ?? '') ?></code><a class="admin-link" href="/admin/infosystems/<?= text($infosystemId) ?>/items/<?= text($item['id'] ?? '') ?>/edit">Изменить</a></div><?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
