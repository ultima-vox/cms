<?php

declare(strict_types=1);

$admin_active = 'structure';
$admin_title = !empty($node['id']) ? 'Редактирование узла' : 'Новый узел';
$admin_styles = ['/assets/structure.css'];
ob_start();
$publishAt = !empty($node['publish_at']) ? (new DateTimeImmutable((string) $node['publish_at']))->format('Y-m-d\TH:i') : '';
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow">Structure</p><h1 class="admin-header__title"><?= text(!empty($node['id']) ? $node['name'] : 'Новый узел') ?></h1><?php if (($node['path'] ?? '') !== ''): ?><p class="admin-page-header__description"><code><?= text($node['path']) ?></code></p><?php endif; ?></div><a class="admin-button admin-button--secondary" href="/admin/structure">К структуре</a></header>
    <?php if (!empty($error)): ?><div class="admin-notice admin-notice--error"><?= text($error) ?></div><?php endif; ?>
    <form class="admin-form" method="post" action="<?= !empty($node['id']) ? '/admin/structure/' . (int) $node['id'] : '/admin/structure' ?>">
        <input type="hidden" name="_csrf" value="<?= text($csrf_token) ?>">
        <section class="admin-panel"><h2 class="admin-panel__title">Основное</h2><div class="admin-form__grid">
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Название</span><input class="admin-input" name="name" required maxlength="255" value="<?= text($node['name'] ?? '') ?>"></label>
            <label class="admin-field"><span class="admin-field__label">Родитель</span><select class="admin-select" name="parent_id"<?= !empty($node['id']) && ($node['parent_id'] ?? null) === null ? ' disabled' : '' ?>><option value="">Корень</option><?php foreach ($nodes as $candidate): ?><?php if (empty($node['id']) || (int) $candidate['id'] !== (int) $node['id']): ?><option value="<?= (int) $candidate['id'] ?>"<?= ($node['parent_id'] ?? null) !== null && (int) $node['parent_id'] === (int) $candidate['id'] ? ' selected' : '' ?>><?= text($candidate['path'] . ' — ' . $candidate['name']) ?></option><?php endif; ?><?php endforeach; ?></select><?php if (!empty($node['id']) && ($node['parent_id'] ?? null) === null): ?><input type="hidden" name="parent_id" value=""><?php endif; ?></label>
            <label class="admin-field"><span class="admin-field__label">Slug</span><input class="admin-input" name="slug" maxlength="255" pattern="[a-z0-9][a-z0-9-]*" value="<?= text($node['slug'] ?? '') ?>"<?= !empty($node['id']) && ($node['parent_id'] ?? null) === null ? ' disabled' : '' ?>><span class="admin-field__hint">a-z, 0-9 и дефис. Для корневого узла пусто.</span></label>
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Title</span><input class="admin-input" name="title" maxlength="255" value="<?= text($node['title'] ?? '') ?>"></label>
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Meta description</span><textarea class="admin-textarea admin-textarea--small" name="meta_description" maxlength="320"><?= text($node['meta_description'] ?? '') ?></textarea></label>
        </div></section>
        <section class="admin-panel"><h2 class="admin-panel__title">Представление</h2><div class="admin-form__grid"><label class="admin-field"><span class="admin-field__label">Макет</span><select class="admin-select" name="layout_id"><option value="">Без макета</option><?php foreach ($layouts as $layout): ?><option value="<?= (int) $layout['id'] ?>"<?= ($node['layout_id'] ?? null) !== null && (int) $node['layout_id'] === (int) $layout['id'] ? ' selected' : '' ?>><?= text($layout['name']) ?></option><?php endforeach; ?></select></label><label class="admin-field admin-field--wide"><span class="admin-field__label">Контент</span><textarea class="admin-textarea" name="content"><?= text($node['content'] ?? '') ?></textarea><span class="admin-field__hint">HTML хранится как редакционный контент и выводится макетом. Привязки к прикладным модулям настраиваются в самих модулях.</span></label></div></section>
        <section class="admin-panel"><h2 class="admin-panel__title">Публикация</h2><div class="admin-form__grid"><label class="admin-field"><span class="admin-field__label">Статус</span><select class="admin-select" name="status"><?php foreach (['draft', 'published', 'archived'] as $status): ?><option value="<?= $status ?>"<?= ($node['status'] ?? '') === $status ? ' selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label><label class="admin-field"><span class="admin-field__label">Дата публикации</span><input class="admin-input" type="datetime-local" name="publish_at" value="<?= text($publishAt) ?>"></label><label class="admin-field"><span class="admin-field__label">Сортировка</span><input class="admin-input" type="number" min="0" name="sorting" value="<?= (int) ($node['sorting'] ?? 0) ?>"></label><label class="admin-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"<?= !empty($node['is_active']) ? ' checked' : '' ?>><span>Узел активен</span></label></div></section>
        <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Сохранить</button><a class="admin-button admin-button--secondary" href="/admin/structure">Отмена</a></div>
    </form>
    <?php if (!empty($node['id']) && ($node['parent_id'] ?? null) !== null): ?><form class="admin-danger-zone" method="post" action="/admin/structure/<?= (int) $node['id'] ?>/delete" onsubmit="return confirm('Удалить узел и всё его поддерево?');"><input type="hidden" name="_csrf" value="<?= text($csrf_token) ?>"><div><strong>Удаление узла</strong><p>Дочерние узлы будут удалены каскадно.</p></div><button class="admin-button admin-button--danger" type="submit">Удалить</button></form><?php endif; ?>
</div>
<?php
$admin_content = (string) ob_get_clean();
require dirname(__DIR__) . '/_shell.php';
