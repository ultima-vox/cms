<?php

declare(strict_types=1);

$adminActive = 'layouts';
$filters = $listing['filters'];
$pageUrl = static fn (int $page): string => '/admin/layouts?' . http_build_query(
    $filters + ['page' => $page], '', '&', PHP_QUERY_RFC3986,
);
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => 'Макеты',
    'admin_styles' => ['/assets/layouts.css?v=' . \Core\Version::STRING],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header">
        <div>
            <p class="admin-eyebrow">Presentation</p>
            <h1 class="admin-header__title">Макеты</h1>
            <p class="admin-page-header__description">Обычные HTML/PHP-макеты без XML/XSLT и отдельного шаблонного языка публичного сайта.</p>
        </div>
        <a class="admin-button admin-button--primary" href="/admin/layouts/create?folder=<?= text($folder_browser['selected'] ?? '') ?>">Новый макет</a>
    </header>

    <?php if (!empty($deleted)): ?><div class="admin-notice admin-notice--success">Макет удалён.</div><?php endif; ?>

    <?= $view->render('admin/folders/browser.php', ['browser' => $folder_browser]) ?>
    <form class="admin-list-filters" method="get" action="/admin/layouts" aria-label="Поиск и фильтры макетов">
        <input type="hidden" name="folder" value="<?= text($filters['folder']) ?>">
        <label class="admin-field"><span>Название или код</span><input class="admin-input" type="search" name="q" maxlength="160" value="<?= text($filters['q']) ?>" placeholder="Поиск в выбранной папке"></label>
        <label class="admin-field"><span id="layouts-sort-label">Сортировка</span><select class="admin-select" name="sort" aria-labelledby="layouts-sort-label">
            <?php foreach (['name' => 'Название: А–Я', 'name_desc' => 'Название: Я–А', 'code' => 'По коду'] as $value => $label): ?>
                <option value="<?= text($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= text($label) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label class="admin-field"><span id="layouts-size-label">На странице</span><select class="admin-select" name="per_page" aria-labelledby="layouts-size-label">
            <?php foreach ([25, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $filters['per_page'] === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?>
        </select></label>
        <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Применить</button><a class="admin-link" href="/admin/layouts?folder=<?= text($filters['folder']) ?>">Сбросить</a></div>
    </form>

    <section class="admin-panel admin-panel--flush">
        <?php if ($layouts === []): ?>
            <div class="admin-empty"><h2 class="admin-panel__title">Ничего не найдено</h2><p>Измените поиск или создайте макет в этой папке.</p></div>
        <?php else: ?>
            <div class="layout-list">
                <div class="layout-list__head"><span>Макет</span><span>Файл</span><span>Узлы</span><span>Тип</span><span></span></div>
                <?php foreach ($layouts as $layout): ?>
                    <div class="layout-row">
                        <div><strong><?= text($layout['name'] ?? '') ?></strong><?php if (!empty($layout['description'])): ?><p class="layout-row__description"><?= text($layout['description']) ?></p><?php endif; ?></div>
                        <code><?= text($layout['template_path'] ?? '') ?></code>
                        <span><span class="layout-row__metric-label">Узлы: </span><?= text($layout['node_count'] ?? 0) ?></span>
                        <span><?php if (!empty($layout['is_system'])): ?><span class="admin-badge">системный</span><?php else: ?>пользовательский<?php endif; ?></span>
                        <a class="admin-link" href="/admin/layouts/<?= text($layout['id'] ?? '') ?>/edit">Изменить</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php if ($listing['total'] > 0): ?>
        <nav class="admin-list-pagination" aria-label="Страницы макетов">
            <p>Показано <?= ($listing['page'] - 1) * $listing['per_page'] + 1 ?>–<?= min($listing['page'] * $listing['per_page'], $listing['total']) ?> из <?= $listing['total'] ?></p>
            <div class="admin-actions">
                <?php if ($listing['page'] > 1): ?><a class="admin-button admin-button--secondary" rel="prev" href="<?= text($pageUrl($listing['page'] - 1)) ?>">Назад</a><?php endif; ?>
                <span aria-current="page">Страница <?= $listing['page'] ?> из <?= $listing['pages'] ?></span>
                <?php if ($listing['page'] < $listing['pages']): ?><a class="admin-button admin-button--secondary" rel="next" href="<?= text($pageUrl($listing['page'] + 1)) ?>">Далее</a><?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
