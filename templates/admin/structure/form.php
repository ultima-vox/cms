<?php

declare(strict_types=1);

$adminActive = 'structure';
$nodeId = $node['id'] ?? null;
$isRoot = $nodeId && ($node['parent_id'] ?? null) === null;
$title = $nodeId ? 'Редактирование узла' : 'Новый узел';
$pageTypes = is_array($page_types ?? null) ? $page_types : [];
$selectedPageType = (string) ($node['page_type'] ?? '');
$alternativePageTypes = array_values(array_filter(
    $pageTypes,
    static fn ($definition): bool => $definition->code !== $selectedPageType,
));
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

    <?php if ($nodeId): ?>
        <section class="admin-panel" aria-label="Содержимое страницы">
            <h2 class="admin-panel__title">Содержимое страницы</h2>
            <?php if (!empty($content_editor_url)): ?>
                <a class="admin-button admin-button--primary" href="<?= text($content_editor_url) ?>">Редактировать содержимое</a>
                <p class="admin-field__hint">Параметры страницы и публикация настраиваются здесь. Материал редактируется в его модуле.</p>
            <?php else: ?>
                <p>Редактор содержимого недоступен: проверьте модуль, материал и права доступа.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <form class="admin-form" method="post" action="<?= text($nodeId ? '/admin/structure/' . $nodeId : '/admin/structure') ?>">
        <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><input type="hidden" name="_site_id" value="<?= text($admin_shell->site()->id) ?>">
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
                <div class="admin-field admin-field--wide"><span class="admin-field__label">Тип страницы</span><code><?= text($selectedPageType) ?></code><span class="admin-field__hint">Обычное сохранение узла не меняет тип страницы. Контент редактируется в модуле, которому принадлежит выбранный тип страницы.</span></div>
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

    <?php if ($nodeId && $alternativePageTypes !== []): ?>
        <form class="admin-form" method="post" action="/admin/structure/<?= text($nodeId) ?>/page-type" data-confirm="Изменить тип страницы? Прежние документы и привязки сохранятся.">
            <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><input type="hidden" name="_site_id" value="<?= text($admin_shell->site()->id) ?>">
            <input type="hidden" name="current_page_type" value="<?= text($selectedPageType) ?>">
            <section class="admin-panel">
                <h2 class="admin-panel__title">Смена типа страницы</h2>
                <p>Прежние документы и привязки сохранятся. Для нового типа будет настроен его ресурс. Типы, требующие отдельной настройки в модуле, здесь недоступны.</p>
                <label class="admin-field"><span class="admin-field__label">Новый тип страницы</span><select class="admin-select" name="page_type" required><?php foreach ($alternativePageTypes as $pageType): ?><option value="<?= text($pageType->code) ?>"><?= text($pageType->name) ?></option><?php endforeach; ?></select></label>
                <div class="admin-actions"><button class="admin-button admin-button--secondary" type="submit">Изменить тип страницы</button></div>
            </section>
        </form>
    <?php endif; ?>

    <?php if ($nodeId && !$isRoot): ?>
        <form class="admin-danger-zone" method="post" action="/admin/structure/<?= text($nodeId) ?>/delete" data-confirm="Удалить узел и всё его поддерево?"><input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><input type="hidden" name="_site_id" value="<?= text($admin_shell->site()->id) ?>"><div><strong>Удаление узла</strong><p>Дочерние узлы и их привязки будут удалены. Документы и другие ресурсы модулей сохранятся.</p></div><button class="admin-button admin-button--danger" type="submit">Удалить</button></form>
    <?php endif; ?>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
