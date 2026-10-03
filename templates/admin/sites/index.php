<?php

declare(strict_types=1);

$adminActive = 'sites';
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => 'Сайты',
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header">
        <div><p class="admin-eyebrow">Sites</p><h1 class="admin-header__title">Сайты</h1><p class="admin-page-header__description">Текущий контекст: <strong><?= text($admin_site->name) ?></strong>. Домены определяют публичный SiteContext.</p></div>
        <a class="admin-button admin-button--primary" href="/admin/sites/create">Новый сайт</a>
    </header>

    <?php if (!empty($saved)): ?><div class="admin-notice admin-notice--success">Изменения сохранены.</div><?php endif; ?>

    <?php if ($sites === []): ?>
        <section class="admin-panel admin-empty"><h2 class="admin-panel__title">Сайты не найдены</h2><p>Создайте первый сайт и назначьте ему публичный домен.</p></section>
    <?php else: ?>
        <section class="admin-panel admin-panel--flush"><div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Сайт</th><th>Домен</th><th>Контент</th><th>Статус</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($sites as $site): ?>
                <tr>
                    <td><span class="admin-table__primary"><?= text($site['name'] ?? '') ?></span><span class="admin-table__secondary"><code><?= text($site['code'] ?? '') ?></code></span></td>
                    <td><?= text(($site['primary_host'] ?? '') ?: '—') ?></td>
                    <td><span class="admin-table__primary"><?= text($site['node_count'] ?? 0) ?> узл.</span><span class="admin-table__secondary"><?= text($site['infosystem_count'] ?? 0) ?> инфосистем · <?= text($site['domain_count'] ?? 0) ?> доменов</span></td>
                    <td>
                        <?php if (!empty($site['is_active'])): ?><span class="admin-badge admin-badge--success">активен</span><?php else: ?><span class="admin-badge">выключен</span><?php endif; ?>
                        <?php if ((int) ($site['id'] ?? 0) === $admin_site->id): ?><span class="admin-badge admin-badge--info">текущий</span><?php endif; ?>
                    </td>
                    <td><div class="admin-actions">
                        <a class="admin-button admin-button--secondary" href="/admin/sites/<?= text($site['id'] ?? '') ?>/edit">Настроить</a>
                        <?php if (!empty($site['is_active']) && (int) ($site['id'] ?? 0) !== $admin_site->id): ?>
                            <form action="/admin/sites/select" method="post"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><input type="hidden" name="site_id" value="<?= text($site['id'] ?? '') ?>"><button class="admin-button admin-button--secondary" type="submit">Выбрать</button></form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div></section>
    <?php endif; ?>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
