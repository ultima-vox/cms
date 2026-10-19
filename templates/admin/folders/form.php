<?php declare(strict_types=1); echo $view->render('admin/_header.php', ['admin_active' => $definition['scope'], 'admin_title' => 'Настройки папки']); ?>
<div class="admin-dashboard"><h1 class="admin-header__title">Настройки папки · <?= text($definition['label']) ?></h1>
<?php if ($error !== null): ?><div class="admin-notice"><?= text($error) ?></div><?php endif; ?>
<form class="admin-form admin-panel" method="post" action="/admin/folders/<?= text($definition['scope']) ?><?= $folder_id !== null ? '/' . text($folder_id) : '' ?>">
    <input type="hidden" name="_csrf" value="<?= text($admin_shell->csrfToken()) ?>"><input type="hidden" name="_site_id" value="<?= text($admin_shell->site()->id) ?>">
    <label class="admin-field"><span>Название</span><input class="admin-input" name="name" required maxlength="160" value="<?= text($folder['name'] ?? '') ?>"></label>
    <label class="admin-field"><span>Родительская папка</span><select class="admin-select" name="parent_id"><option value="">Корневая папка</option><?php foreach ($options as $option): if ((int) $option['id'] === $folder_id) continue; ?><option value="<?= text($option['id']) ?>"<?= (int) ($folder['parent_id'] ?? 0) === (int) $option['id'] ? ' selected' : '' ?>><?= text($option['label']) ?></option><?php endforeach; ?></select></label>
    <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Сохранить</button><a class="admin-button" href="<?= text($definition['path']) ?>">К материалам</a></div>
</form>
<?php if ($folder_id !== null): ?><form method="post" action="/admin/folders/<?= text($definition['scope']) ?>/<?= text($folder_id) ?>/delete" data-confirm="Удалить пустую папку?"><input type="hidden" name="_csrf" value="<?= text($admin_shell->csrfToken()) ?>"><input type="hidden" name="_site_id" value="<?= text($admin_shell->site()->id) ?>"><button class="admin-button admin-button--danger" type="submit">Удалить папку</button><p>Содержимое и дочерние папки сначала нужно переместить.</p></form><?php endif; ?>
</div><?= $view->render('admin/_footer.php') ?>
