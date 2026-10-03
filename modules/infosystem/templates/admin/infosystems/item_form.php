<?php

declare(strict_types=1);

$adminActive = 'infosystems';
$infosystemId = (int) ($infosystem['id'] ?? 0);
$itemId = $item['id'] ?? null;
$publishValue = '';
if (!empty($item['publish_at'])) {
    $timestamp = strtotime((string) $item['publish_at']);
    $publishValue = $timestamp !== false ? date('Y-m-d\TH:i', $timestamp) : '';
}
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => $itemId ? 'Редактирование элемента' : 'Новый элемент',
    'admin_styles' => ['/assets/infosystems.css'],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow"><?= text($infosystem['code'] ?? '') ?></p><h1 class="admin-header__title"><?= text($itemId ? ($item['name'] ?? '') : 'Новый элемент') ?></h1><?php if (!empty($item['path'])): ?><p class="admin-page-header__description"><code><?= text($item['path']) ?></code></p><?php endif; ?></div><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>">К инфосистеме</a></header>
    <?php if (!empty($saved)): ?><div class="admin-notice">Элемент сохранён.</div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="admin-notice admin-notice--error"><?= text($error) ?></div><?php endif; ?>

    <form class="admin-form" method="post" action="<?= text($itemId ? '/admin/infosystems/' . $infosystemId . '/items/' . $itemId : '/admin/infosystems/' . $infosystemId . '/items') ?>">
        <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>">
        <section class="admin-panel"><h2 class="admin-panel__title">Основное</h2><div class="admin-form__grid">
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Название</span><input class="admin-input" name="name" required maxlength="255" value="<?= text($item['name'] ?? '') ?>"></label>
            <label class="admin-field"><span class="admin-field__label">Группа</span><select class="admin-select" name="group_id"><option value="">Корень инфосистемы</option><?php foreach ($groups as $group): ?><option value="<?= text($group['id'] ?? '') ?>"<?= (int) ($item['group_id'] ?? 0) === (int) ($group['id'] ?? 0) ? ' selected' : '' ?>><?= text($group['path'] ?? '') ?> — <?= text($group['name'] ?? '') ?></option><?php endforeach; ?></select></label>
            <label class="admin-field"><span class="admin-field__label">Slug</span><input class="admin-input" name="slug" required maxlength="255" pattern="[a-z0-9][a-z0-9-]*" value="<?= text($item['slug'] ?? '') ?>"></label>
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Краткое описание</span><textarea class="admin-textarea admin-textarea--small" name="description"><?= text($item['description'] ?? '') ?></textarea></label>
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Контент</span><textarea class="admin-textarea admin-textarea--content" name="content"><?= text($item['content'] ?? '') ?></textarea></label>
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Meta description</span><textarea class="admin-textarea admin-textarea--small" name="meta_description" maxlength="320"><?= text($item['meta_description'] ?? '') ?></textarea></label>
        </div></section>

        <?php if (($fields ?? []) !== []): ?>
            <section class="admin-panel"><h2 class="admin-panel__title">Дополнительные свойства</h2><div class="admin-form__grid">
                <?php foreach ($fields as $field): ?>
                    <?php $code = (string) ($field['code'] ?? ''); $value = $item['properties'][$code] ?? null; $required = !empty($field['required']); $type = (string) ($field['type'] ?? 'text'); ?>
                    <?php if ($type === 'textarea'): ?>
                        <label class="admin-field admin-field--wide"><span class="admin-field__label"><?= text($field['name'] ?? '') ?><?= $required ? ' *' : '' ?></span><textarea class="admin-textarea admin-textarea--small" name="properties[<?= text($code) ?>]"<?= $required ? ' required' : '' ?>><?= text(is_scalar($value) ? $value : '') ?></textarea></label>
                    <?php elseif ($type === 'boolean'): ?>
                        <label class="admin-check"><input type="hidden" name="properties[<?= text($code) ?>]" value="0"><input type="checkbox" name="properties[<?= text($code) ?>]" value="1"<?= $value ? ' checked' : '' ?>><span><?= text($field['name'] ?? '') ?></span></label>
                    <?php elseif ($type === 'select'): ?>
                        <label class="admin-field"><span class="admin-field__label"><?= text($field['name'] ?? '') ?><?= $required ? ' *' : '' ?></span><select class="admin-select" name="properties[<?= text($code) ?>]"<?= $required ? ' required' : '' ?>><option value="">—</option><?php foreach ((array) ($field['options'] ?? []) as $option): ?><option value="<?= text($option) ?>"<?= $value === $option ? ' selected' : '' ?>><?= text($option) ?></option><?php endforeach; ?></select></label>
                    <?php else: ?>
                        <?php $inputType = $type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text'); ?>
                        <label class="admin-field"><span class="admin-field__label"><?= text($field['name'] ?? '') ?><?= $required ? ' *' : '' ?></span><input class="admin-input" name="properties[<?= text($code) ?>]" value="<?= text(is_scalar($value) ? $value : '') ?>" type="<?= text($inputType) ?>"<?= $type === 'number' ? ' step="any"' : '' ?><?= $required ? ' required' : '' ?>></label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div></section>
        <?php endif; ?>

        <section class="admin-panel"><h2 class="admin-panel__title">Публикация</h2><div class="admin-form__grid">
            <label class="admin-field"><span class="admin-field__label">Статус</span><select class="admin-select" name="status"><?php foreach (['draft','published','archived'] as $status): ?><option value="<?= text($status) ?>"<?= ($item['status'] ?? '') === $status ? ' selected' : '' ?>><?= text($status) ?></option><?php endforeach; ?></select></label>
            <label class="admin-field"><span class="admin-field__label">Опубликовать не раньше</span><input class="admin-input" type="datetime-local" name="publish_at" value="<?= text($publishValue) ?>"></label>
            <label class="admin-field"><span class="admin-field__label">Сортировка</span><input class="admin-input" type="number" min="0" name="sorting" value="<?= text($item['sorting'] ?? 0) ?>"></label>
            <label class="admin-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"<?= !empty($item['is_active']) ? ' checked' : '' ?>><span>Элемент активен</span></label>
        </div></section>

        <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Сохранить</button><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>">Отмена</a></div>
    </form>

    <?php if ($itemId): ?><section class="admin-danger-zone"><div><strong>Удаление элемента</strong><p>Действие необратимо.</p></div><form method="post" action="/admin/infosystems/<?= text($infosystemId) ?>/items/<?= text($itemId) ?>/delete" onsubmit="return confirm('Удалить элемент?');"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><button class="admin-button admin-button--danger" type="submit">Удалить</button></form></section><?php endif; ?>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
