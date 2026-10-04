<?php

declare(strict_types=1);

$adminActive = 'modules';
$moduleCode = (string) ($module['code'] ?? '');
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => 'Удаление данных ' . $moduleCode,
    'admin_styles' => ['/assets/modules.css'],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header">
        <div><p class="admin-eyebrow">Destructive operation</p><h1 class="admin-header__title">Полностью удалить <?= text($module['name'] ?? '') ?></h1><p class="admin-page-header__description">Эта операция удалит код пакета и данные, объявленные модулем в purge-контракте. Откат после успешного commit не предусмотрен.</p></div>
        <a class="admin-button admin-button--secondary" href="/admin/modules">Отмена</a>
    </header>
    <section class="admin-danger-zone module-purge-panel"><div class="module-purge-warning"><strong><?= text($moduleCode) ?> <?= text($module['version'] ?? '') ?></strong><p>Будут выполнены destructive SQL scripts:</p><ul><?php foreach (($module['purge_scripts'] ?? []) as $script): ?><li><code><?= text($script) ?></code></li><?php endforeach; ?></ul></div></section>
    <section class="admin-panel" style="margin-top:20px">
        <form class="admin-form module-purge-form" method="post" action="/admin/modules/<?= text($moduleCode) ?>/purge" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>">
            <label class="admin-field"><span class="admin-field__label">Для подтверждения введите точно: <code><?= text($moduleCode) ?></code></span><input class="admin-input" type="text" name="confirmation" required spellcheck="false" autocomplete="off"></label>
            <div class="admin-actions"><button class="admin-button admin-button--danger" type="submit">Необратимо удалить модуль и его данные</button></div>
        </form>
    </section>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
