<?php

declare(strict_types=1);

$adminActive = 'documents';
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => 'Документы',
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header">
        <div>
            <p class="admin-eyebrow">Контент</p>
            <h1 class="admin-header__title">Документы</h1>
            <p class="admin-page-header__description">Тексты страниц и повторно используемые материалы.</p>
        </div>
        <a class="admin-button admin-button--primary" href="<?= text($list_query->createUrl($admin_shell->site()->id, $listing['page'])) ?>">Новый документ</a>
    </header>

    <?php if (!empty($created)): ?>
        <div class="admin-notice admin-notice--success">Документ создан.</div>
    <?php endif; ?>

    <?= $view->render('admin/folders/browser.php', ['browser' => $folder_browser]) ?>

    <form class="admin-list-filters" method="get" action="/admin/documents" aria-label="Поиск и фильтры документов">
        <input type="hidden" name="folder" value="<?= text($list_query->allFolders ? 'all' : ($list_query->folderId ?? '')) ?>">
        <label class="admin-field"><span>Название или код</span><input class="admin-input" type="search" name="q" maxlength="160" value="<?= text($list_query->search) ?>" placeholder="Поиск в выбранной папке"></label>
        <label class="admin-field"><span id="documents-status-label">Публикация</span><select class="admin-select" name="status" aria-labelledby="documents-status-label">
            <?php foreach (['' => 'Все статусы', 'published' => 'Есть публикация', 'draft' => 'Есть черновик', 'unpublished' => 'Без публикации'] as $value => $label): ?>
                <option value="<?= text($value) ?>" <?= $list_query->status === $value ? 'selected' : '' ?>><?= text($label) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label class="admin-field"><span id="documents-sort-label">Сортировка</span><select class="admin-select" name="sort" aria-labelledby="documents-sort-label">
            <?php foreach (['name' => 'Название: А–Я', 'name_desc' => 'Название: Я–А', 'code' => 'По коду', 'updated' => 'Сначала изменённые'] as $value => $label): ?>
                <option value="<?= text($value) ?>" <?= $list_query->sort === $value ? 'selected' : '' ?>><?= text($label) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label class="admin-field"><span id="documents-size-label">На странице</span><select class="admin-select" name="per_page" aria-labelledby="documents-size-label">
            <?php foreach ([25, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $list_query->perPage === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?>
        </select></label>
        <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Применить</button><a class="admin-link" href="/admin/documents?folder=<?= text($list_query->allFolders ? 'all' : ($list_query->folderId ?? '')) ?>">Сбросить</a></div>
    </form>

    <section class="admin-panel">

        <?php if ($documents === []): ?>
            <div class="admin-empty-state">
                <?php if ($list_query->search !== '' || $list_query->status !== ''): ?>
                    <h2>Ничего не найдено</h2><p>Измените запрос или сбросьте фильтры. Поиск ограничен выбранной папкой.</p>
                <?php else: ?>
                    <h2><?= $list_query->allFolders ? 'Документов пока нет' : 'В этой папке пока нет документов' ?></h2><p>Создайте документ или откройте «Все материалы».</p>
                <?php endif; ?>
                <a class="admin-button admin-button--primary" href="<?= text($list_query->createUrl($admin_shell->site()->id, $listing['page'])) ?>">Создать документ</a>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>Документ</th>
                        <th>Код</th>
                        <th>Опубликовано</th>
                        <th>Черновик</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $document): ?>
                        <tr>
                            <td><a class="admin-link" href="<?= text($list_query->editUrl((int) $document['id'], $admin_shell->site()->id, $listing['page'])) ?>"><strong><?= text($document['name'] ?? '') ?></strong></a></td>
                            <td><code><?= text($document['code'] ?? '') ?></code></td>
                            <td><?= !empty($document['published_version']) ? 'v' . text($document['published_version']) : '—' ?></td>
                            <td><?= !empty($document['draft_version']) ? 'v' . text($document['draft_version']) : '—' ?></td>
                            <td class="admin-table__actions"><a class="admin-button admin-button--secondary" href="<?= text($list_query->editUrl((int) $document['id'], $admin_shell->site()->id, $listing['page'])) ?>">Редактировать</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php if ($listing['total'] > 0): ?>
        <nav class="admin-list-pagination" aria-label="Страницы документов">
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
