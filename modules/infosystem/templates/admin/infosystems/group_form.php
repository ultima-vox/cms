<?php

declare(strict_types=1);

$adminActive = 'infosystems';
$infosystemId = (int) ($infosystem['id'] ?? 0);
$groupId = $group['id'] ?? null;
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => $groupId ? 'Редактирование группы' : 'Новая группа',
    'admin_styles' => ['/assets/infosystems.css'],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow"><?= text($infosystem['code'] ?? '') ?></p><h1 class="admin-header__title"><?= text($groupId ? ($group['name'] ?? '') : 'Новая группа') ?></h1><?php if (!empty($group['path'])): ?><p class="admin-page-header__description"><code><?= text($group['path']) ?></code></p><?php endif; ?></div><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>">К инфосистеме</a></header>
    <?php if (!empty($error)): ?><div class="admin-notice admin-notice--error"><?= text($error) ?></div><?php endif; ?>
    <form class="admin-form" method="post" action="<?= text($groupId ? '/admin/infosystems/' . $infosystemId . '/groups/' . $groupId : '/admin/infosystems/' . $infosystemId . '/groups') ?>">
        <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>">
        <section class="admin-panel"><h2 class="admin-panel__title">Группа</h2><div class="admin-form__grid">
            <label class="admin-field"><span class="admin-field__label">Название</span><input class="admin-input" name="name" required maxlength="255" value="<?= text($group['name'] ?? '') ?>"></label>
            <label class="admin-field"><span class="admin-field__label">Slug</span><input class="admin-input" name="slug" required maxlength="255" pattern="[a-z0-9][a-z0-9-]*" value="<?= text($group['slug'] ?? '') ?>"></label>
            <label class="admin-field"><span class="admin-field__label">Родитель</span><select class="admin-select" name="parent_id"><option value="">Корень</option><?php foreach ($groups as $candidate): ?><?php if (!$groupId || (int) ($candidate['id'] ?? 0) !== (int) $groupId): ?><option value="<?= text($candidate['id'] ?? '') ?>"<?= (int) ($group['parent_id'] ?? 0) === (int) ($candidate['id'] ?? 0) ? ' selected' : '' ?>><?= text($candidate['path'] ?? '') ?> — <?= text($candidate['name'] ?? '') ?></option><?php endif; ?><?php endforeach; ?></select></label>
            <label class="admin-field"><span class="admin-field__label">Сортировка</span><input class="admin-input" type="number" min="0" name="sorting" value="<?= text($group['sorting'] ?? 0) ?>"></label>
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Описание</span><textarea class="admin-textarea admin-textarea--small" name="description"><?= text($group['description'] ?? '') ?></textarea></label>
            <label class="admin-check admin-field--wide"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"<?= !empty($group['is_active']) ? ' checked' : '' ?>><span>Группа активна</span></label>
        </div></section>
        <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Сохранить</button><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>">Отмена</a></div>
    </form>
    <?php if ($groupId): ?>
        <?php $blocked = (int) ($group['child_count'] ?? 0) > 0 || (int) ($group['item_count'] ?? 0) > 0; ?>
        <section class="admin-danger-zone"><div><strong>Удаление группы</strong><p><?= $blocked ? 'Сначала удалите или перенесите дочерние группы и элементы.' : 'Пустую группу можно удалить безопасно.' ?></p></div><form method="post" action="/admin/infosystems/<?= text($infosystemId) ?>/groups/<?= text($groupId) ?>/delete" onsubmit="return confirm('Удалить группу?');"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><button class="admin-button admin-button--danger" type="submit"<?= $blocked ? ' disabled' : '' ?>>Удалить</button></form></section>
    <?php endif; ?>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
