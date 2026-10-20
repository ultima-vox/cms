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
    <header class="admin-page-header"><div><p class="admin-eyebrow">Контент</p><h1 class="admin-header__title">Инфосистемы</h1><p class="admin-page-header__description">Каталоги, новости, услуги и другие структурированные наборы контента.</p></div><a class="admin-button admin-button--primary" href="/admin/infosystems/create?folder=<?= text($folder_browser['selected'] ?? '') ?>">Новая инфосистема</a></header>
    <?php if (!empty($deleted)): ?><div class="admin-notice admin-notice--success">Инфосистема удалена.</div><?php endif; ?>
    <?= $view->render('admin/folders/browser.php', ['browser' => $folder_browser]) ?>

    <form class="admin-list-filters" method="get" action="/admin/infosystems" aria-label="Поиск и фильтры инфосистем">
        <input type="hidden" name="folder" value="<?= text($list_query->allFolders ? 'all' : ($list_query->folderId ?? '')) ?>">
        <label class="admin-field"><span>Название или код</span><input class="admin-input" type="search" name="q" maxlength="160" value="<?= text($list_query->search) ?>" placeholder="Поиск в выбранной папке"></label>
        <label class="admin-field"><span id="infosystems-status-label">Активность</span><select class="admin-select" name="status" aria-labelledby="infosystems-status-label">
            <?php foreach (['' => 'Все', 'active' => 'Активные', 'inactive' => 'Отключённые'] as $value => $label): ?>
                <option value="<?= text($value) ?>" <?= $list_query->status === $value ? 'selected' : '' ?>><?= text($label) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label class="admin-field"><span id="infosystems-sort-label">Сортировка</span><select class="admin-select" name="sort" aria-labelledby="infosystems-sort-label">
            <?php foreach (['name' => 'Название: А–Я', 'name_desc' => 'Название: Я–А', 'code' => 'По коду'] as $value => $label): ?>
                <option value="<?= text($value) ?>" <?= $list_query->sort === $value ? 'selected' : '' ?>><?= text($label) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label class="admin-field"><span id="infosystems-size-label">На странице</span><select class="admin-select" name="per_page" aria-labelledby="infosystems-size-label">
            <?php foreach ([25, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $list_query->perPage === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?>
        </select></label>
        <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Применить</button><a class="admin-link" href="/admin/infosystems?folder=<?= text($list_query->allFolders ? 'all' : ($list_query->folderId ?? '')) ?>">Сбросить</a></div>
    </form>

    <section class="admin-panel admin-panel--flush">
        <?php if ($infosystems === []): ?>
            <div class="admin-empty"><h2 class="admin-panel__title">Ничего не найдено</h2><p>Измените поиск или фильтр либо создайте инфосистему в этой папке.</p></div>
        <?php else: ?>
            <div class="infosystem-list__head"><span>Название</span><span>Код</span><span>Группы</span><span>Элементы</span><span>Узлы</span><span></span></div>
            <?php foreach ($infosystems as $system): ?>
                <div class="infosystem-row"><div><a class="admin-link infosystem-row__name" href="/admin/infosystems/<?= text($system['id'] ?? '') ?>"><?= text($system['name'] ?? '') ?></a><?php if (empty($system['is_active'])): ?> <span class="admin-muted">Отключена</span><?php endif; ?><?php if (!empty($system['description'])): ?><p class="infosystem-row__description"><?= text($system['description']) ?></p><?php endif; ?></div><code><?= text($system['code'] ?? '') ?></code><span class="infosystem-row__metric"><span class="infosystem-row__metric-label">Группы</span> <strong><?= text($system['group_count'] ?? 0) ?></strong></span><span class="infosystem-row__metric"><span class="infosystem-row__metric-label">Элементы</span> <strong><?= text($system['item_count'] ?? 0) ?></strong></span><span class="infosystem-row__metric"><span class="infosystem-row__metric-label">Узлы</span> <strong><?= text($system['node_count'] ?? 0) ?></strong></span><a class="admin-button admin-button--secondary" href="/admin/infosystems/<?= text($system['id'] ?? '') ?>/edit">Настройки</a></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <?php if ($listing['total'] > 0): ?>
        <nav class="admin-list-pagination" aria-label="Страницы инфосистем">
            <p>Показано <?= ($listing['page'] - 1) * $listing['per_page'] + 1 ?>–<?= min($listing['page'] * $listing['per_page'], $listing['total']) ?> из <?= $listing['total'] ?></p>
            <div class="admin-actions">
                <?php if ($listing['page'] > 1): ?><a class="admin-button admin-button--secondary" rel="prev" href="<?= text($list_query->url($listing['page'] - 1)) ?>">Назад</a><?php endif; ?>
                <span aria-current="page">Страница <?= $listing['page'] ?> из <?= $listing['pages'] ?></span>
                <?php if ($listing['page'] < $listing['pages']): ?><a class="admin-button admin-button--secondary" rel="next" href="<?= text($list_query->url($listing['page'] + 1)) ?>">Далее</a><?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
