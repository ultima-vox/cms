<?php

declare(strict_types=1);

$adminActive = 'infosystems';
$infosystemId = $infosystem['id'] ?? null;
$title = $infosystemId ? 'Настройки инфосистемы' : 'Новая инфосистема';
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => $title,
    'admin_styles' => ['/assets/infosystems.css'],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header">
        <div><p class="admin-eyebrow">Infosystem</p><h1 class="admin-header__title"><?= text($infosystemId ? ($infosystem['name'] ?? '') : 'Новая инфосистема') ?></h1><?php if ($infosystemId): ?><p class="admin-page-header__description"><code><?= text($infosystem['code'] ?? '') ?></code></p><?php endif; ?></div>
        <div class="admin-actions"><?php if ($infosystemId): ?><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>/bindings">Разделы сайта</a><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>">К контенту</a><?php endif; ?><a class="admin-button" href="/admin/infosystems">К списку</a></div>
    </header>

    <?php if (!empty($saved)): ?><div class="admin-notice">Настройки сохранены.</div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="admin-notice admin-notice--error"><?= text($error) ?></div><?php endif; ?>

    <form class="admin-form" method="post" action="<?= text($infosystemId ? '/admin/infosystems/' . $infosystemId : '/admin/infosystems') ?>" id="infosystem-form">
        <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>">
        <section class="admin-panel"><h2 class="admin-panel__title">Основное</h2><div class="admin-form__grid">
            <label class="admin-field"><span class="admin-field__label">Название</span><input class="admin-input" name="name" maxlength="255" required value="<?= text($infosystem['name'] ?? '') ?>"></label>
            <label class="admin-field"><span class="admin-field__label">Код</span><input class="admin-input" name="code" maxlength="120" pattern="[a-z][a-z0-9_-]*" required value="<?= text($infosystem['code'] ?? '') ?>"<?= $infosystemId ? ' readonly' : '' ?>><span class="admin-field__hint">Стабильный системный идентификатор. После создания не меняется.</span></label>
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Описание</span><textarea class="admin-textarea admin-textarea--small" name="description"><?= text($infosystem['description'] ?? '') ?></textarea></label>
            <label class="admin-check admin-field--wide"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"<?= !empty($infosystem['is_active']) ? ' checked' : '' ?>><span>Инфосистема активна</span></label>
        </div></section>

        <section class="admin-panel">
            <div class="admin-panel-heading"><div><h2 class="admin-panel__title">Дополнительные поля</h2><p class="admin-field__hint">Определения хранятся одной JSONB-схемой. Значения элементов — в <code>properties JSONB</code>.</p></div><button class="admin-button" type="button" id="add-field">Добавить поле</button></div>
            <div class="field-schema" id="field-schema">
                <?php foreach ((array) ($infosystem['field_schema'] ?? []) as $index => $field): ?>
                    <?php $options = $field['options'] ?? ''; $optionsValue = is_array($options) ? implode(', ', $options) : (string) $options; ?>
                    <div class="field-schema__row" data-field-row>
                        <span class="field-schema__drag" title="Порядок полей">⋮⋮</span>
                        <input class="admin-input" name="fields[<?= text($index) ?>][name]" placeholder="Название" value="<?= text($field['name'] ?? '') ?>" required data-name="name">
                        <input class="admin-input" name="fields[<?= text($index) ?>][code]" placeholder="code" pattern="[a-z][a-z0-9_]*" value="<?= text($field['code'] ?? '') ?>" required data-name="code">
                        <select class="admin-select" name="fields[<?= text($index) ?>][type]" data-name="type"><?php foreach (['text','textarea','number','boolean','select','date'] as $type): ?><option value="<?= text($type) ?>"<?= ($field['type'] ?? '') === $type ? ' selected' : '' ?>><?= text($type) ?></option><?php endforeach; ?></select>
                        <input class="admin-input" name="fields[<?= text($index) ?>][options]" placeholder="Опции через запятую" value="<?= text($optionsValue) ?>" data-name="options">
                        <label class="field-schema__check"><input type="hidden" name="fields[<?= text($index) ?>][required]" value="0" data-name="required-hidden"><input type="checkbox" name="fields[<?= text($index) ?>][required]" value="1"<?= !empty($field['required']) ? ' checked' : '' ?> data-name="required"> обяз.</label>
                        <label class="field-schema__check"><input type="hidden" name="fields[<?= text($index) ?>][filterable]" value="0" data-name="filterable-hidden"><input type="checkbox" name="fields[<?= text($index) ?>][filterable]" value="1"<?= !empty($field['filterable']) ? ' checked' : '' ?> data-name="filterable"> фильтр</label>
                        <button class="field-schema__remove" type="button" aria-label="Удалить поле" data-remove-field>×</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="admin-field__hint">Для типа <code>select</code> укажите опции через запятую. Флаг «фильтр» документирует поля, предназначенные для публичной фильтрации через GIN.</p>
        </section>

        <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Сохранить</button><a class="admin-button" href="<?= text($infosystemId ? '/admin/infosystems/' . $infosystemId : '/admin/infosystems') ?>">Отмена</a></div>
    </form>

    <?php if ($infosystemId): ?>
        <section class="admin-danger-zone"><div><strong>Удаление инфосистемы</strong><p><?= (int) ($infosystem['node_count'] ?? 0) > 0 ? 'Сначала отвяжите её от всех разделов сайта.' : 'Будут удалены все группы и элементы этой инфосистемы.' ?></p></div><form method="post" action="/admin/infosystems/<?= text($infosystemId) ?>/delete" onsubmit="return confirm('Удалить инфосистему вместе со всем контентом?');"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><button class="admin-button admin-button--danger" type="submit"<?= (int) ($infosystem['node_count'] ?? 0) > 0 ? ' disabled' : '' ?>>Удалить</button></form></section>
    <?php endif; ?>
</div>

<template id="field-template">
    <div class="field-schema__row" data-field-row><span class="field-schema__drag">⋮⋮</span><input class="admin-input" placeholder="Название" required data-name="name"><input class="admin-input" placeholder="code" pattern="[a-z][a-z0-9_]*" required data-name="code"><select class="admin-select" data-name="type"><option value="text">text</option><option value="textarea">textarea</option><option value="number">number</option><option value="boolean">boolean</option><option value="select">select</option><option value="date">date</option></select><input class="admin-input" placeholder="Опции через запятую" data-name="options"><label class="field-schema__check"><input type="hidden" value="0" data-name="required-hidden"><input type="checkbox" value="1" data-name="required"> обяз.</label><label class="field-schema__check"><input type="hidden" value="0" data-name="filterable-hidden"><input type="checkbox" value="1" data-name="filterable"> фильтр</label><button class="field-schema__remove" type="button" aria-label="Удалить поле" data-remove-field>×</button></div>
</template>
<script>
(() => {
    const container = document.getElementById('field-schema');
    const template = document.getElementById('field-template');
    const add = document.getElementById('add-field');
    const rename = () => {
        [...container.querySelectorAll('[data-field-row]')].forEach((row, index) => {
            row.querySelectorAll('[data-name]').forEach((input) => {
                const key = input.dataset.name.replace('-hidden', '');
                input.name = `fields[${index}][${key}]`;
            });
        });
    };
    add.addEventListener('click', () => { container.append(template.content.cloneNode(true)); rename(); });
    container.addEventListener('click', (event) => { const button = event.target.closest('[data-remove-field]'); if (!button) return; button.closest('[data-field-row]').remove(); rename(); });
    rename();
})();
</script>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
