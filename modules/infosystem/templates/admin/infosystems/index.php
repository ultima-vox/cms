<?php

declare(strict_types=1);

$adminActive = 'infosystems';
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => 'Инфосистемы',
    'admin_styles' => ['/assets/infosystems.css'],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow">Content storage</p><h1 class="admin-header__title">Инфосистемы</h1><p class="admin-page-header__description">Каталоги, новости, услуги и другие структурированные наборы контента.</p></div><a class="admin-button admin-button--primary" href="/admin/infosystems/create">Новая инфосистема</a></header>
    <?php if (!empty($deleted)): ?><div class="admin-notice admin-notice--success">Инфосистема удалена.</div><?php endif; ?>
    <section class="admin-panel admin-panel--flush">
        <?php if ($infosystems === []): ?>
            <div class="admin-empty"><h2 class="admin-panel__title">Инфосистем пока нет</h2><p>Создайте структурированный набор контента для текущего сайта.</p></div>
        <?php else: ?>
            <div class="infosystem-list__head"><span>Название</span><span>Код</span><span>Группы</span><span>Элементы</span><span>Узлы</span><span></span></div>
            <?php foreach ($infosystems as $system): ?>
                <div class="infosystem-row"><div><a class="admin-link infosystem-row__name" href="/admin/infosystems/<?= text($system['id'] ?? '') ?>"><?= text($system['name'] ?? '') ?></a><?php if (!empty($system['description'])): ?><p class="infosystem-row__description"><?= text($system['description']) ?></p><?php endif; ?></div><code><?= text($system['code'] ?? '') ?></code><span><?= text($system['group_count'] ?? 0) ?></span><span><?= text($system['item_count'] ?? 0) ?></span><span><?= text($system['node_count'] ?? 0) ?></span><a class="admin-button admin-button--secondary" href="/admin/infosystems/<?= text($system['id'] ?? '') ?>/edit">Настройки</a></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
