<?php

declare(strict_types=1);

$adminActive = 'infosystems';
$infosystemId = (int) ($infosystem['id'] ?? 0);
$groupId = (int) ($selected_group_id ?? 0);
$base = '/admin/infosystems/' . $infosystemId;
$folderUrl = static fn (int $id): string => $base . ($id > 0 ? '?group_id=' . $id : '');
$query = $groupId > 0 ? '?group_id=' . $groupId : '';
$groupTotal = (int) ($infosystem['group_count'] ?? 0);
$itemTotal = (int) ($infosystem['item_count'] ?? 0);
$selectedName = is_array($selected_group ?? null) ? (string) $selected_group['name'] : (string) ($infosystem['name'] ?? '');
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => $selectedName,
    'admin_styles' => ['/assets/infosystems.css'],
]);
?>
<div class="admin-dashboard">
    <?php if ($page_return !== null): ?><a class="admin-button" href="<?= text($page_return['url']) ?>">К странице</a><?php endif; ?>
    <header class="admin-page-header">
        <div>
            <p class="admin-eyebrow"><?= text((string) ($infosystem['code'] ?? '')) ?> · Информационная система</p>
            <h1 class="admin-header__title"><?= text($selectedName) ?></h1>
            <nav class="is-breadcrumbs" aria-label="Путь в информационной системе">
                <a href="<?= text($base) ?>"><?= text((string) ($infosystem['name'] ?? 'Корень')) ?></a>
                <?php foreach ($breadcrumbs ?? [] as $crumb): ?>
                    <span aria-hidden="true">›</span>
                    <?php if ((int) $crumb['id'] === $groupId): ?>
                        <strong aria-current="page"><?= text((string) $crumb['name']) ?></strong>
                    <?php else: ?>
                        <a href="<?= text($folderUrl((int) $crumb['id'])) ?>"><?= text((string) $crumb['name']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="admin-actions">
            <?php if ($groupId > 0): ?><a class="admin-button" href="<?= text($base . '/groups/' . $groupId . '/edit') ?>">Изменить группу</a><?php endif; ?>
            <a class="admin-button" href="<?= text($base . '/edit') ?>">Настройки ИС</a>
            <a class="admin-button" href="/admin/infosystems">Все инфосистемы</a>
        </div>
    </header>
    <?php if (!empty($created)): ?><div class="admin-notice" role="status">Инфосистема создана.</div><?php endif; ?>
    <?php if (!empty($saved)): ?><div class="admin-notice" role="status">Изменения сохранены.</div><?php endif; ?>
    <?php if (!empty($deleted)): ?><div class="admin-notice" role="status">Объект удалён.</div><?php endif; ?>

    <div class="content-stats">
        <div class="content-stat"><strong><?= text($groupTotal) ?></strong><span>групп всего</span></div>
        <div class="content-stat"><strong><?= text($itemTotal) ?></strong><span>элементов всего</span></div>
        <div class="content-stat"><strong><?= text(count(is_array($infosystem['field_schema'] ?? null) ? $infosystem['field_schema'] : [])) ?></strong><span>дополнительных полей</span></div>
    </div>

    <section class="admin-panel admin-panel--flush content-section" aria-label="Содержимое группы">
        <div class="content-section__header">
            <div>
                <h2 class="admin-panel__title">Содержимое <?= $groupId > 0 ? 'группы' : 'инфосистемы' ?></h2>
                <p class="admin-field__hint"><?= count($groups ?? []) ?> вложенных групп · <?= text($item_total ?? 0) ?> элементов непосредственно здесь.
                    <?php if ($groupId > 0): ?> Элементы остальных групп не смешиваются с текущей.<?php endif; ?>
                </p>
            </div>
            <div class="admin-actions">
                <a class="admin-button" href="<?= text($base . '/groups/create' . ($groupId > 0 ? '?parent_id=' . $groupId : '')) ?>">+ Новая группа</a>
                <a class="admin-button admin-button--primary" href="<?= text($base . '/items/create' . ($groupId > 0 ? '?group_id=' . $groupId : '')) ?>">+ Новый элемент</a>
            </div>
        </div>

        <?php if (($groups ?? []) === [] && ($items ?? []) === []): ?>
            <div class="admin-empty">В этом разделе пока пусто. Создайте вложенную группу или первый элемент.</div>
        <?php else: ?>
            <div class="is-browser__head" aria-hidden="true"><span>Название</span><span>Тип</span><span>Элементов</span><span>Путь</span><span></span></div>
            <div class="is-browser__list">
                <?php if ($groupId > 0): ?>
                    <?php $parentId = (int) ($selected_group['parent_id'] ?? 0); ?>
                    <a class="is-browser__parent" href="<?= text($folderUrl($parentId)) ?>">
                        <span class="is-browser__folder-icon" aria-hidden="true">↰</span> На уровень выше
                    </a>
                <?php endif; ?>

                <?php foreach ($groups ?? [] as $group): ?>
                    <div class="is-browser__row is-browser__row--group">
                        <div class="is-browser__name">
                            <span class="is-browser__folder-icon" aria-hidden="true">▣</span>
                            <a href="<?= text($folderUrl((int) $group['id'])) ?>"><strong><?= text((string) $group['name']) ?></strong></a>
                            <?php if (!(bool) ($group['is_active'] ?? false)): ?><span class="admin-badge">Неактивна</span><?php endif; ?>
                        </div>
                        <span class="content-row__muted">Группа<?= (int) ($group['child_count'] ?? 0) > 0 ? ' · подгрупп: ' . (int) $group['child_count'] : '' ?></span>
                        <span><?= text((int) ($group['item_count'] ?? 0)) ?></span>
                        <code><?= text((string) ($group['path'] ?? '')) ?></code>
                        <a class="admin-link" href="<?= text($base . '/groups/' . (int) $group['id'] . '/edit') ?>" aria-label="Изменить группу <?= text((string) $group['name']) ?>">Изменить</a>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($items ?? [] as $item): ?>
                    <div class="is-browser__row is-browser__row--item">
                        <div class="is-browser__name">
                            <span class="is-browser__item-icon" aria-hidden="true">▤</span>
                            <a href="<?= text($base . '/items/' . (int) $item['id'] . '/edit') ?>"><strong><?= text((string) $item['name']) ?></strong></a>
                        </div>
                        <span class="content-row__muted">Элемент</span>
                        <span><span class="admin-status admin-status--<?= text((string) ($item['status'] ?? 'draft')) ?>"><?= text((string) ($item['status'] ?? 'draft')) ?></span></span>
                        <code><?= text((string) ($item['path'] ?? '')) ?></code>
                        <a class="admin-link" href="<?= text($base . '/items/' . (int) $item['id'] . '/edit') ?>" aria-label="Изменить элемент <?= text((string) $item['name']) ?>">Изменить</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="is-browser__footer">
            <a class="admin-link" href="<?= text($base . '/items' . ($groupId > 0 ? '?group_id=' . $groupId : '')) ?>">Поиск, фильтры и полный список →</a>
            <?php if (($pages ?? 1) > 1): ?>
                <nav class="admin-pagination" aria-label="Страницы элементов">
                    <?php if (($page ?? 1) > 1): ?><a class="admin-button" href="<?= text($base . '?group_id=' . $groupId . '&page=' . ((int) $page - 1)) ?>">← Назад</a><?php endif; ?>
                    <span class="admin-pagination__summary"><?= text($page) ?> / <?= text($pages) ?></span>
                    <?php if (($page ?? 1) < $pages): ?><a class="admin-button" href="<?= text($base . '?group_id=' . $groupId . '&page=' . ((int) $page + 1)) ?>">Далее →</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </section>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
