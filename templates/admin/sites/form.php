<?php

declare(strict_types=1);

$adminActive = 'sites';
$siteId = $site['id'] ?? null;
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => $siteId ? 'Настройка сайта' : 'Новый сайт',
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header">
        <div><p class="admin-eyebrow">Sites</p><h1 class="admin-header__title"><?= text($siteId ? ($site['name'] ?? '') : 'Новый сайт') ?></h1><?php if ($siteId): ?><p class="admin-page-header__description">Код <code><?= text($site['code'] ?? '') ?></code> после создания не изменяется.</p><?php endif; ?></div>
        <a class="admin-button admin-button--secondary" href="/admin/sites">К списку</a>
    </header>

    <?php if (!empty($error)): ?><div class="admin-notice admin-notice--error"><?= text($error) ?></div><?php endif; ?>
    <?php if (!empty($saved)): ?><div class="admin-notice admin-notice--success">Изменения сохранены.</div><?php endif; ?>

    <form class="admin-form" action="<?= text($siteId ? '/admin/sites/' . $siteId : '/admin/sites') ?>" method="post">
        <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>">
        <section class="admin-panel"><h2 class="admin-panel__title">Основное</h2><div class="admin-form__grid">
            <label class="admin-field<?= $siteId ? ' admin-field--wide' : '' ?>"><span class="admin-field__label">Название</span><input class="admin-input" name="name" value="<?= text($site['name'] ?? '') ?>" maxlength="255" required></label>
            <?php if ($siteId): ?>
                <div class="admin-field"><span class="admin-field__label">Код</span><code><?= text($site['code'] ?? '') ?></code><span class="admin-field__hint">Стабильный идентификатор сайта.</span></div>
                <label class="admin-check"><input type="checkbox" name="is_active" value="1"<?= !empty($site['is_active']) ? ' checked' : '' ?><?= ($site['code'] ?? '') === 'default' ? ' disabled' : '' ?>><span>Сайт активен</span></label>
                <?php if (($site['code'] ?? '') === 'default'): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
            <?php else: ?>
                <label class="admin-field"><span class="admin-field__label">Код</span><input class="admin-input" name="code" value="<?= text($site['code'] ?? '') ?>" maxlength="120" pattern="[a-z][a-z0-9_-]*" required><span class="admin-field__hint">a-z, 0-9, дефис и подчёркивание. После создания не меняется.</span></label>
            <?php endif; ?>
        </div></section>
        <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Сохранить</button></div>
    </form>

    <?php if ($siteId): ?>
        <section class="admin-panel" style="margin-top:20px">
            <div class="admin-panel__header"><div><h2 class="admin-panel__title">Домены</h2><p class="admin-panel__text">Домен хранится без схемы и порта. Один домен сайта может быть основным.</p></div></div>
            <?php if ($domains === []): ?>
                <div class="admin-empty"><p>Домены ещё не добавлены. Для default-сайта продолжает работать host из APP_URL.</p></div>
            <?php else: ?>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Host</th><th>Тип</th><th></th></tr></thead><tbody>
                <?php foreach ($domains as $domain): ?>
                    <tr><td><code><?= text($domain['host'] ?? '') ?></code></td><td><?php if (!empty($domain['is_primary'])): ?><span class="admin-badge admin-badge--success">основной</span><?php else: ?><span class="admin-badge">alias</span><?php endif; ?></td><td><div class="admin-actions">
                        <?php if (empty($domain['is_primary'])): ?><form action="/admin/sites/<?= text($siteId) ?>/domains/<?= text($domain['id'] ?? '') ?>/primary" method="post"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><button class="admin-button admin-button--secondary" type="submit">Сделать основным</button></form><?php endif; ?>
                        <form action="/admin/sites/<?= text($siteId) ?>/domains/<?= text($domain['id'] ?? '') ?>/delete" method="post"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><button class="admin-button admin-button--danger" type="submit">Удалить</button></form>
                    </div></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
            <form class="admin-form" action="/admin/sites/<?= text($siteId) ?>/domains" method="post" style="margin-top:20px"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><div class="admin-form__grid"><label class="admin-field"><span class="admin-field__label">Новый домен</span><input class="admin-input" name="host" placeholder="example.ru" maxlength="253" required></label><label class="admin-check"><input type="checkbox" name="is_primary" value="1"><span>Сделать основным</span></label></div><div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Добавить домен</button></div></form>
        </section>
    <?php endif; ?>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
