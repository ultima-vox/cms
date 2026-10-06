<?php

declare(strict_types=1);

$adminActive = 'structure';
$nodeId = $node['id'] ?? null;
$isRoot = $nodeId && ($node['parent_id'] ?? null) === null;
$title = $nodeId ? 'Редактирование узла' : 'Новый узел';
$pageTypes = is_array($page_types ?? null) ? $page_types : [];
$selectedPageType = (string) ($node['page_type'] ?? '');
$publishValue = '';
if (!empty($node['publish_at'])) {
    $timestamp = strtotime((string) $node['publish_at']);
    $publishValue = $timestamp !== false ? date('Y-m-d\TH:i', $timestamp) : '';
}
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => $title,
    'admin_styles' => ['/assets/structure.css'],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header">
        <div><p class="admin-eyebrow">Structure</p><h1 class="admin-header__title"><?= text($nodeId ? ($node['name'] ?? '') : 'Новый узел') ?></h1><?php if (!empty($node['path'])): ?><p class="admin-page-header__description"><code><?= text($node['path']) ?></code></p><?php endif; ?></div>
        <a class="admin-button admin-button--secondary" href="/admin/structure">К структуре</a>
    </header>

    <?php if (!empty($error)): ?><div class="admin-notice admin-notice--error"><?= text($error) ?></div><?php endif; ?>

    <form class="admin-form" method="post" action="<?= text($nodeId ? '/admin/structure/' . $nodeId : '/admin/structure') ?>">
        <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>">
        <section class="admin-panel"><h2 class="admin-panel__title">Основное</h2><div class="admin-form__grid">
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Название</span><input class="admin-input" name="name" required maxlength="255" value="<?= text($node['name'] ?? '') ?>"></label>
            <label class="admin-field"><span class="admin-field__label">Родитель</span><select class="admin-select" name="parent_id"<?= $isRoot ? ' disabled' : '' ?>><option value="">Корень</option><?php foreach ($nodes as $candidate): ?><?php if (!$nodeId || (int) ($candidate['id'] ?? 0) !== (int) $nodeId): ?><option value="<?= text($candidate['id'] ?? '') ?>"<?= (int) ($node['parent_id'] ?? 0) === (int) ($candidate['id'] ?? 0) ? ' selected' : '' ?>><?= text($candidate['path'] ?? '') ?> — <?= text($candidate['name'] ?? '') ?></option><?php endif; ?><?php endforeach; ?></select><?php if ($isRoot): ?><input type="hidden" name="parent_id" value=""><?php endif; ?></label>
            <label class="admin-field"><span class="admin-field__label">Slug</span><input class="admin-input" name="slug" maxlength="255" pattern="[a-z0-9][a-z0-9-]*" value="<?= text($node['slug'] ?? '') ?>"<?= $isRoot ? ' disabled' : '' ?>><span class="admin-field__hint">a-z, 0-9 и дефис. Для корневого узла пусто.</span></label>
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Title</span><input class="admin-input" name="title" maxlength="255" value="<?= text($node['title'] ?? '') ?>"></label>
            <label class="admin-field admin-field--wide"><span class="admin-field__label">Meta description</span><textarea class="admin-textarea admin-textarea--small" name="meta_description" maxlength="320"><?= text($node['meta_description'] ?? '') ?></textarea></label>
        </div></section>

        <section class="admin-panel"><h2 class="admin-panel__title">Представление</h2><div class="admin-form__grid">
            <label class="admin-field"><span class="admin-field__label">Макет</span><select class="admin-select" name="layout_id"><option value="">Без макета</option><?php foreach ($layouts as $layout): ?><option value="<?= text($layout['id'] ?? '') ?>"<?= (int) ($node['layout_id'] ?? 0) === (int) ($layout['id'] ?? 0) ? ' selected' : '' ?>><?= text($layout['name'] ?? '') ?></option><?php endforeach; ?></select></label>
            <?php if (!$nodeId): ?>
                <label class="admin-field admin-field--wide"><span class="admin-field__label">Тип страницы</span><select class="admin-select" name="page_type" required><?php foreach ($pageTypes as $pageType): ?><option value="<?= text($pageType->code) ?>"<?= $selectedPageType === $pageType->code ? ' selected' : '' ?>><?= text($pageType->name) ?> — <?= text($pageType->code) ?></option><?php endforeach; ?></select><span class="admin-field__hint">Доступны только типы, которые умеют самостоятельно создать и настроить свой ресурс. Бизнес-логика остаётся внутри соответствующего модуля.</span></label>
            <?php else: ?>
                <div class="admin-field admin-field--wide"><span class="admin-field__label">Тип страницы</span><code><?= text($selectedPageType) ?></code><span class="admin-field__hint">Смена типа страницы будет отдельной безопасной операцией; обычное сохранение узла не меняет владельца контента.</span></div>
                <label class="admin-field admin-field--wide"><span class="admin-field__label">Встроенный контент узла</span><textarea class="admin-textarea" name="content"><?= text($node['content'] ?? '') ?></textarea><span class="admin-field__hint">Поле совместимости для существующих типов страниц. Новые типы должны хранить свой контент в принадлежащем им модуле.</span></label>
            <?php endif; ?>
        </div></section>

        <section class="admin-panel"><h2 class="admin-panel__title">Публикация</h2><div class="admin-form__grid">
            <label class="admin-field"><span class="admin-field__label">Статус</span><select class="admin-select" name="status"><?php foreach (['draft', 'published', 'archived'] as $status): ?><option value="<?= text($status) ?>"<?= ($node['status'] ?? '') === $status ? ' selected' : '' ?>><?= text($status) ?></option><?php endforeach; ?></select></label>
            <label class="admin-field"><span class="admin-field__label">Дата публикации</span><input class="admin-input" type="datetime-local" name="publish_at" value="<?= text($publishValue) ?>"></label>
            <label class="admin-field"><span class="admin-field__label">Сортировка</span><input class="admin-input" type="number" min="0" name="sorting" value="<?= text($node['sorting'] ?? 0) ?>"></label>
            <label class="admin-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"<?= !empty($node['is_active']) ? ' checked' : '' ?>><span>Узел активен</span></label>
        </div></section>

        <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Сохранить</button><a class="admin-button admin-button--secondary" href="/admin/structure">Отмена</a></div>
    </form>

    <?php if ($nodeId && !$isRoot): ?>
        <form class="admin-danger-zone" method="post" action="/admin/structure/<?= text($nodeId) ?>/delete" onsubmit="return confirm('Удалить узел и всё его поддерево?');"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><div><strong>Удаление узла</strong><p>Дочерние узлы будут удалены каскадно.</p></div><button class="admin-button admin-button--danger" type="submit">Удалить</button></form>
    <?php endif; ?>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
