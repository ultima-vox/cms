<?php

declare(strict_types=1);

$adminActive = 'infosystems';
$infosystemId = (int) ($infosystem['id'] ?? 0);
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => 'Элементы ' . (string) ($infosystem['name'] ?? ''),
    'admin_styles' => ['/assets/infosystems.css'],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow"><?= text($infosystem['code'] ?? '') ?></p><h1 class="admin-header__title">Элементы</h1><p class="admin-page-header__description"><?= text($infosystem['name'] ?? '') ?> · найдено <?= text($pagination['total'] ?? 0) ?></p></div><div class="admin-actions"><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>">К инфосистеме</a><a class="admin-button admin-button--primary" href="/admin/infosystems/<?= text($infosystemId) ?>/items/create">Новый элемент</a></div></header>

    <section class="admin-panel"><form class="item-filters" method="get" action="/admin/infosystems/<?= text($infosystemId) ?>/items">
        <label class="admin-field item-filters__search"><span class="admin-field__label">Поиск</span><input class="admin-input" name="q" value="<?= text($filters['q'] ?? '') ?>" placeholder="Название, slug или путь"></label>
        <label class="admin-field"><span class="admin-field__label">Группа</span><select class="admin-select" name="group_id"><option value="">Все группы</option><?php foreach ($groups as $group): ?><option value="<?= text($group['id'] ?? '') ?>"<?= (int) ($filters['group_id'] ?? 0) === (int) ($group['id'] ?? 0) ? ' selected' : '' ?>><?= text($group['path'] ?? '') ?></option><?php endforeach; ?></select></label>
        <label class="admin-field"><span class="admin-field__label">Статус</span><select class="admin-select" name="status"><option value="">Все</option><?php foreach (['draft','published','archived'] as $status): ?><option value="<?= text($status) ?>"<?= ($filters['status'] ?? '') === $status ? ' selected' : '' ?>><?= text($status) ?></option><?php endforeach; ?></select></label>
        <label class="admin-field"><span class="admin-field__label">На странице</span><select class="admin-select" name="per_page"><?php foreach ([25,50,100,200] as $size): ?><option value="<?= text($size) ?>"<?= (int) ($pagination['per_page'] ?? 0) === $size ? ' selected' : '' ?>><?= text($size) ?></option><?php endforeach; ?></select></label>
        <?php foreach ($filterable_fields as $field): ?>
            <?php $code = (string) ($field['code'] ?? ''); $filterValue = $filters['properties'][$code] ?? null; ?>
            <label class="admin-field"><span class="admin-field__label"><?= text($field['name'] ?? '') ?></span>
                <?php if (($field['type'] ?? '') === 'select'): ?><select class="admin-select" name="properties[<?= text($code) ?>]"><option value="">Любое значение</option><?php foreach ((array) ($field['options'] ?? []) as $option): ?><option value="<?= text($option) ?>"<?= $filterValue === $option ? ' selected' : '' ?>><?= text($option) ?></option><?php endforeach; ?></select>
                <?php elseif (($field['type'] ?? '') === 'boolean'): ?><select class="admin-select" name="properties[<?= text($code) ?>]"><option value="">Любое</option><option value="1"<?= $filterValue === true ? ' selected' : '' ?>>Да</option><option value="0"<?= $filterValue === false ? ' selected' : '' ?>>Нет</option></select>
                <?php else: ?><input class="admin-input" name="properties[<?= text($code) ?>]" value="<?= text(is_scalar($filterValue) ? $filterValue : '') ?>" type="<?= ($field['type'] ?? '') === 'number' ? 'number' : (($field['type'] ?? '') === 'date' ? 'date' : 'text') ?>"><?php endif; ?>
            </label>
        <?php endforeach; ?>
        <div class="admin-actions item-filters__actions"><button class="admin-button admin-button--primary" type="submit">Применить</button><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>/items">Сбросить</a></div>
    </form></section>

    <section class="admin-panel admin-panel--flush content-section">
        <?php if (($items ?? []) === []): ?><div class="admin-empty">По заданным условиям элементы не найдены.</div><?php else: ?><div class="content-table__head content-table__head--items"><span>Название</span><span>Группа</span><span>Статус</span><span>Путь</span><span></span></div><?php foreach ($items as $item): ?><div class="content-row content-row--items"><div><strong><?= text($item['name'] ?? '') ?></strong><?php if (empty($item['is_active'])): ?> <span class="admin-badge">выкл.</span><?php endif; ?></div><span class="content-row__muted"><?= text(($item['group_name'] ?? '') ?: 'Корень') ?></span><span class="admin-status admin-status--<?= text($item['status'] ?? '') ?>"><?= text($item['status'] ?? '') ?></span><code><?= text($item['path'] ?? '') ?></code><a class="admin-link" href="/admin/infosystems/<?= text($infosystemId) ?>/items/<?= text($item['id'] ?? '') ?>/edit">Изменить</a></div><?php endforeach; ?><?php endif; ?>
    </section>

    <?php if ((int) ($pagination['pages'] ?? 1) > 1): ?>
        <?php $query = (string) ($pagination['query'] ?? ''); $separator = $query !== '' ? '&' : ''; $page = (int) ($pagination['page'] ?? 1); $pages = (int) ($pagination['pages'] ?? 1); ?>
        <nav class="admin-pagination" aria-label="Страницы"><?php if ($page > 1): ?><a class="admin-button" href="?<?= text($query . $separator . 'page=' . ($page - 1)) ?>">← Назад</a><?php endif; ?><span class="admin-pagination__summary">Страница <?= text($page) ?> из <?= text($pages) ?></span><?php if ($page < $pages): ?><a class="admin-button" href="?<?= text($query . $separator . 'page=' . ($page + 1)) ?>">Далее →</a><?php endif; ?></nav>
    <?php endif; ?>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
