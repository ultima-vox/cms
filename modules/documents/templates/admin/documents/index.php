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
            <p class="admin-eyebrow">Content</p>
            <h1 class="admin-header__title">Документы</h1>
            <p class="admin-page-header__description">Переиспользуемый HTML-контент с версионированием и стабильными кодами.</p>
        </div>
        <a class="admin-button admin-button--primary" href="/admin/documents/create">Новый документ</a>
    </header>

    <?php if (!empty($created)): ?>
        <div class="admin-notice admin-notice--success">Документ создан.</div>
    <?php endif; ?>

    <section class="admin-panel">
        <?php if ($documents === []): ?>
            <div class="admin-empty-state">
                <h2>Документов пока нет</h2>
                <p>Создайте документ, чтобы использовать его в странице или PHP-макете.</p>
                <a class="admin-button admin-button--primary" href="/admin/documents/create">Создать документ</a>
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
                            <td><strong><?= text($document['name'] ?? '') ?></strong></td>
                            <td><code><?= text($document['code'] ?? '') ?></code></td>
                            <td><?= !empty($document['published_version']) ? 'v' . text($document['published_version']) : '—' ?></td>
                            <td><?= !empty($document['draft_version']) ? 'v' . text($document['draft_version']) : '—' ?></td>
                            <td class="admin-table__actions"><a class="admin-button admin-button--secondary" href="/admin/documents/<?= text($document['id'] ?? '') ?>/edit">Редактировать</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
