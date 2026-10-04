<?php

declare(strict_types=1);

$adminActive = 'modules';
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => 'Модули',
    'admin_styles' => ['/assets/modules.css'],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header">
        <div><p class="admin-eyebrow">System</p><h1 class="admin-header__title">Модули</h1><p class="admin-page-header__description">Установка и обновление пакетов, состояние модулей, зависимости и lifecycle-операции.</p></div>
        <form method="post" action="/admin/modules/sync"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><button class="admin-button admin-button--secondary" type="submit">Синхронизировать</button></form>
    </header>

    <?php if (!empty($notice)): ?><div class="admin-notice admin-notice--success"><?= text($notice) ?></div><?php endif; ?>

    <div class="admin-dashboard-grid">
        <section class="admin-panel module-upload-panel">
            <div><p class="admin-eyebrow">Package install</p><h2 class="admin-panel__title">Установить ZIP-пакет</h2><p class="module-upload-panel__description">Новый пакет устанавливается выключенным. После установки выполните синхронизацию, миграции и включение. Для подписанного пакета приложите detached <code>.sig</code>.</p></div>
            <form class="module-upload-form" method="post" action="/admin/modules/install" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>">
                <label class="admin-field"><span class="admin-field__label">Пакет (.zip)</span><input class="admin-input" type="file" name="package" accept=".zip,application/zip" required></label>
                <label class="admin-field"><span class="admin-field__label">Подпись (.sig), опционально</span><input class="admin-input" type="file" name="signature" accept=".sig,application/json,text/plain"></label>
                <button class="admin-button admin-button--primary" type="submit">Проверить и установить</button>
            </form>
        </section>
        <section class="admin-panel module-upload-panel">
            <div><p class="admin-eyebrow">Package update</p><h2 class="admin-panel__title">Обновить пакет</h2><p class="module-upload-panel__description">Модуль должен быть package-managed, синхронизирован и выключен. Версия в ZIP обязана быть выше установленной.</p></div>
            <form class="module-upload-form" method="post" action="/admin/modules/update" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>">
                <label class="admin-field"><span class="admin-field__label">Новая версия (.zip)</span><input class="admin-input" type="file" name="package" accept=".zip,application/zip" required></label>
                <label class="admin-field"><span class="admin-field__label">Подпись (.sig), опционально</span><input class="admin-input" type="file" name="signature" accept=".sig,application/json,text/plain"></label>
                <button class="admin-button admin-button--secondary" type="submit">Проверить и обновить</button>
            </form>
        </section>
    </div>

    <section class="admin-panel admin-panel--flush" style="margin-top:18px">
        <?php if ($modules === []): ?>
            <div class="admin-empty"><h2 class="admin-panel__title">Модули не обнаружены</h2><p>Загрузите пакет выше или проверьте каталог <code>modules/</code>.</p></div>
        <?php else: ?>
            <div class="module-list">
                <div class="module-list__head"><span>Модуль</span><span>Версия</span><span>Состояние</span><span>Источник</span><span>Зависимости</span><span>Действия</span></div>
                <?php foreach ($modules as $module): ?>
                    <article class="module-row">
                        <div><strong><?= text($module['name'] ?? '') ?></strong><code class="module-code"><?= text($module['code'] ?? '') ?></code></div>
                        <div><strong><?= text($module['version'] ?? '') ?></strong><span class="module-meta">API <?= text($module['extension_api'] ?? '') ?></span></div>
                        <div><?php if (empty($module['synchronized'])): ?><span class="admin-badge admin-badge--warning">не синхронизирован</span><?php elseif (!empty($module['enabled'])): ?><span class="admin-badge admin-badge--success">включён</span><?php else: ?><span class="admin-badge">выключен</span><?php endif; ?></div>
                        <div><?= !empty($module['package_managed']) ? '<span class="admin-badge">package</span>' : '<span class="module-meta">bundled/project</span>' ?></div>
                        <div class="module-deps">
                            <?php if (($module['requires'] ?? []) === []): ?><span class="module-meta">нет</span><?php else: ?><?php foreach ($module['requires'] as $dependency => $constraint): ?><code><?= text($dependency) ?> <?= text($constraint) ?></code><?php endforeach; ?><?php endif; ?>
                        </div>
                        <div class="module-actions">
                            <form method="post" action="/admin/modules/<?= text($module['code']) ?>/migrate"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><button class="admin-button admin-button--small admin-button--secondary" type="submit">Миграции</button></form>
                            <?php if (!empty($module['synchronized'])): ?>
                                <?php if (!empty($module['enabled'])): ?>
                                    <form method="post" action="/admin/modules/<?= text($module['code']) ?>/disable"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><button class="admin-button admin-button--small admin-button--secondary" type="submit">Выключить</button></form>
                                <?php else: ?>
                                    <form method="post" action="/admin/modules/<?= text($module['code']) ?>/enable"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><button class="admin-button admin-button--small admin-button--primary" type="submit">Включить</button></form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($module['package_managed']) && !empty($module['synchronized']) && empty($module['enabled'])): ?>
                                <form method="post" action="/admin/modules/<?= text($module['code']) ?>/remove" onsubmit="return confirm('Удалить код модуля <?= text($module['code']) ?>? Данные будут сохранены.')"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><button class="admin-button admin-button--small admin-button--danger" type="submit">Удалить пакет</button></form>
                                <?php if (!empty($module['purge_available'])): ?><a class="admin-button admin-button--small admin-button--danger" href="/admin/modules/<?= text($module['code']) ?>/purge">Удалить с данными</a><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
