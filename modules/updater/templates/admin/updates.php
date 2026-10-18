<?php

declare(strict_types=1);
echo $view->render('admin/_header.php', ['admin_active' => 'updates', 'admin_title' => 'Обновления CMS']);
$available = $current !== null && $offer !== null && version_compare($offer['version'], $current['version'], '>');
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow">Vox CMS · канал разработки</p><h1 class="admin-header__title">Обновления</h1><p>Проверьте новую версию, прочитайте изменения и установите обновление.</p></div></header>
    <?php if ($error !== null): ?><div class="admin-notice"><?= text($error) ?></div><?php endif; ?>
    <?php if ($updated): ?><div class="admin-notice admin-notice--success">CMS обновлена. Резервная копия заменённых файлов сохранена.</div><?php endif; ?>
    <section class="admin-panel">
        <h2 class="admin-panel__title">Установлена версия <?= text($current['version'] ?? 'не определена') ?></h2>
        <p>Источник: проверенные CI-релизы репозитория ultima-vox/cms. Автоматической установки нет.</p>
        <form method="post" action="/admin/updates/check"><input type="hidden" name="_csrf" value="<?= text($admin_shell->csrfToken()) ?>"><button class="admin-button" type="submit">Проверить обновления</button></form>
    </section>
    <?php if ($offer !== null): ?>
        <section class="admin-panel" style="margin-top:18px">
            <h2 class="admin-panel__title"><?= $available ? 'Доступна версия ' . text($offer['version']) : 'Последняя проверка: обновление не требуется' ?></h2>
            <h3>Изменения в релизе</h3><ul><?php foreach ($offer['changes'] as $change): ?><li><?= text($change) ?></li><?php endforeach; ?></ul>
            <?php if ($available): ?>
                <p>Будут заменены файлы поставки CMS. Конфигурация, пользовательские файлы и данные сохраняются. Локальные изменения кода и изменение схемы БД блокируют установку.</p>
                <p>Требуются PHP zip, исходящий HTTPS, свободное место для архива и копии файлов, а также запись PHP в каталоги кода. Для нескольких серверов используйте централизованный деплой.</p>
                <form method="post" action="/admin/updates/apply" data-update-form><input type="hidden" name="_csrf" value="<?= text($admin_shell->csrfToken()) ?>"><input type="hidden" name="version" value="<?= text($offer['version']) ?>"><button class="admin-button admin-button--primary" type="submit">Обновить до <?= text($offer['version']) ?></button><span role="status" aria-live="polite"></span></form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<script>
    document.querySelector('[data-update-form]')?.addEventListener('submit', event => {
        event.currentTarget.querySelector('button').disabled = true;
        event.currentTarget.querySelector('[role="status"]').textContent = 'Обновление выполняется. Не закрывайте страницу.';
    });
</script>
<?= $view->render('admin/_footer.php') ?>
