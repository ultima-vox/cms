<?php

declare(strict_types=1);

$admin_active = 'layouts';
$admin_title = !empty($layout['id']) ? 'Редактирование макета' : 'Новый макет';
$admin_styles = ['/assets/layouts.css'];
ob_start();
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow">Layout</p><h1 class="admin-header__title"><?= text(!empty($layout['id']) ? $layout['name'] : 'Новый макет') ?></h1><?php if (($layout['template_path'] ?? '') !== ''): ?><p class="admin-page-header__description"><code><?= text($layout['template_path']) ?></code></p><?php endif; ?></div><a class="admin-button admin-button--secondary" href="/admin/layouts">К макетам</a></header>
    <?php if (!empty($saved)): ?><div class="admin-notice admin-notice--success">Макет сохранён.</div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="admin-notice admin-notice--error"><?= text($error) ?></div><?php endif; ?>
    <form class="admin-form" method="post" action="<?= !empty($layout['id']) ? '/admin/layouts/' . (int) $layout['id'] : '/admin/layouts' ?>">
        <input type="hidden" name="_csrf" value="<?= text($csrf_token) ?>">
        <section class="admin-panel"><h2 class="admin-panel__title">Основное</h2><div class="admin-form__grid">
            <label class="admin-field"><span class="admin-field__label">Название</span><input class="admin-input" name="name" maxlength="255" required value="<?= text($layout['name'] ?? '') ?>"></label>
            <label class="admin-field"><span class="admin-field__label">Код макета</span><input class="admin-input" name="code" maxlength="80" pattern="[a-z0-9][a-z0-9_-]*" required value="<?= text($layout['code'] ?? '') ?>"<?= !empty($layout['id']) ? ' readonly' : '' ?>><span class="admin-field__hint">Новые макеты: <code>layouts/&lt;code&gt;.php</code>. После создания код не меняется.</span></label>
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Описание</span><textarea class="admin-textarea admin-textarea--small" name="description"><?= text($layout['description'] ?? '') ?></textarea></label>
        </div></section>
        <section class="admin-panel"><div class="layout-editor__header"><div><h2 class="admin-panel__title">HTML + PHP шаблон</h2><p class="admin-field__hint">Обычный HTML с точечными PHP-вставками. Доступны <code>$page</code> и зарегистрированные facade-переменные модулей. Для текста используйте <code>text()</code>, для разрешённого HTML — <code>html()</code>.</p></div><?php if (!empty($layout['is_system'])): ?><span class="admin-badge"><?= !empty($layout['has_override']) ? 'runtime override' : 'штатная версия' ?></span><?php endif; ?></div><textarea class="admin-textarea layout-editor" name="source" spellcheck="false" required><?= text($layout['source'] ?? '') ?></textarea><p class="admin-field__hint">Синтаксис PHP проверяется до записи. Изменения сохраняются в <code>storage/templates/layouts</code>; поставка CMS остаётся read-only.</p></section>
        <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Сохранить</button><a class="admin-button admin-button--secondary" href="/admin/layouts">Отмена</a></div>
    </form>
    <?php if (!empty($layout['id'])): ?><section class="layout-usage"><strong>Использование:</strong> <?= (int) ($layout['node_count'] ?? 0) ?> узл. <?php if (!empty($layout['is_system'])): ?><span class="admin-badge">системный макет</span><?php endif; ?></section><?php endif; ?>
    <?php if (!empty($layout['id']) && !empty($layout['is_system']) && !empty($layout['has_override'])): ?><form class="admin-reset-zone" method="post" action="/admin/layouts/<?= (int) $layout['id'] ?>/reset" onsubmit="return confirm('Вернуть штатную версию макета из поставки CMS?');"><input type="hidden" name="_csrf" value="<?= text($csrf_token) ?>"><div><strong>Вернуть штатный шаблон</strong><p>Runtime-override будет удалён, после чего CMS снова использует файл из поставки.</p></div><button class="admin-button admin-button--secondary" type="submit">Вернуть штатный</button></form><?php endif; ?>
    <?php if (!empty($layout['id']) && empty($layout['is_system'])): ?><form class="admin-danger-zone" method="post" action="/admin/layouts/<?= (int) $layout['id'] ?>/delete" onsubmit="return confirm('Удалить макет и его файл?');"><input type="hidden" name="_csrf" value="<?= text($csrf_token) ?>"><div><strong>Удаление макета</strong><p><?= (int) ($layout['node_count'] ?? 0) > 0 ? 'Сначала назначьте другой макет всем использующим его узлам.' : 'Макет и его runtime-файл будут удалены.' ?></p></div><button class="admin-button admin-button--danger" type="submit"<?= (int) ($layout['node_count'] ?? 0) > 0 ? ' disabled' : '' ?>>Удалить</button></form><?php endif; ?>
</div>
<?php
$admin_content = (string) ob_get_clean();
require dirname(__DIR__) . '/_shell.php';
