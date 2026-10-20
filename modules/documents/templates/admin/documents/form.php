<?php

declare(strict_types=1);

$adminActive = 'documents';
$documentId = isset($document['id']) && is_numeric($document['id']) ? (int) $document['id'] : null;
$isCreate = $documentId === null;
$title = $isCreate ? 'Новый документ' : 'Редактирование документа';
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => $title,
    'admin_styles' => ['/assets/code-formatter.css'],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header">
        <div>
            <p class="admin-eyebrow">Documents</p>
            <h1 class="admin-header__title"><?= text($isCreate ? 'Новый документ' : ($document['name'] ?? 'Документ')) ?></h1>
            <?php if (!$isCreate): ?>
                <p class="admin-page-header__description">Код: <code><?= text($document['code'] ?? '') ?></code></p>
            <?php endif; ?>
        </div>
        <a class="admin-button admin-button--secondary" href="<?= text($return_url) ?>">К документам</a>
        <?php if ($page_return !== null): ?><a class="admin-button" href="<?= text($page_return['url']) ?>">К странице</a><?php endif; ?>
    </header>

    <?php if (!empty($error)): ?>
        <div class="admin-notice admin-notice--error" role="alert"><?= text($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($saved)): ?>
        <div class="admin-notice admin-notice--success">Черновик сохранён новой версией.</div>
    <?php endif; ?>
    <?php if (!empty($published)): ?>
        <div class="admin-notice admin-notice--success">Новая версия опубликована.</div>
    <?php endif; ?>

    <form class="admin-form admin-form--actions" data-submit-pending="Сохранение выполняется…" method="post" action="<?= text($isCreate ? '/admin/documents' : '/admin/documents/' . $documentId) ?>">
        <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>"><input type="hidden" name="_site_id" value="<?= text($admin_shell->site()->id) ?>">
        <?php foreach ($page_return['parameters'] ?? [] as $key => $value): ?><input type="hidden" name="<?= text($key) ?>" value="<?= text($value) ?>"><?php endforeach; ?>
        <?php if ($return_parameters !== []): ?>
            <input type="hidden" name="_list_site" value="<?= text($return_parameters['_list_site']) ?>">
            <?php foreach ($return_parameters['_list'] as $key => $value): ?>
                <input type="hidden" name="_list[<?= text($key) ?>]" value="<?= text($value) ?>">
            <?php endforeach; ?>
        <?php endif; ?>
        <?= $view->render('admin/folders/select.php', ['options' => $folder_options, 'selected' => $document['folder_id'] ?? null]) ?>

        <section class="admin-panel">
            <h2 class="admin-panel__title">Документ</h2>
            <div class="admin-form__grid">
                <label class="admin-field admin-field--wide">
                    <span class="admin-field__label">Название</span>
                    <input class="admin-input" name="name" required maxlength="255" value="<?= text($document['name'] ?? '') ?>">
                </label>

                <?php if ($isCreate): ?>
                    <label class="admin-field admin-field--wide">
                        <span class="admin-field__label">Код</span>
                        <input class="admin-input" name="code" required maxlength="120" pattern="[a-z][a-z0-9_-]*" value="<?= text($document['code'] ?? '') ?>">
                        <span class="admin-field__hint">Стабильный технический код: например <code>footer</code> или <code>company-requisites</code>. После создания не меняется.</span>
                    </label>
                <?php else: ?>
                    <div class="admin-field admin-field--wide">
                        <span class="admin-field__label">Код</span>
                        <code><?= text($document['code'] ?? '') ?></code>
                        <span class="admin-field__hint">Код неизменяемый: на него могут ссылаться PHP-макеты и page_config.</span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-panel">
            <h2 class="admin-panel__title">Содержимое</h2>
            <?php if (!$isCreate && is_array($editable_version ?? null)): ?>
                <p class="admin-panel__description">Основа редактора: версия v<?= text($editable_version['version'] ?? '') ?>, статус <?= text($editable_version['status'] ?? '') ?>.</p>
            <?php endif; ?>
            <label class="admin-field admin-field--wide">
                <span class="admin-field__label">HTML</span>
                <textarea class="admin-textarea" name="content" rows="20" data-code-format data-code-language="html"><?= text($document['content'] ?? '') ?></textarea>
                <span class="admin-field__hint">При каждом сохранении создаётся новая версия. Опубликованная версия не изменяется на месте.</span>
            </label>
        </section>

        <div class="admin-actions admin-actions--sticky" role="group" aria-label="Действия документа">
            <button class="admin-button admin-button--secondary" type="submit" name="save_mode" value="draft">Сохранить черновик</button>
            <button class="admin-button admin-button--primary" type="submit" name="save_mode" value="publish">Опубликовать новую версию</button>
            <a class="admin-button admin-button--secondary" href="<?= text($page_return['url'] ?? $return_url) ?>">Отмена</a>
            <span role="status" aria-live="polite"></span>
        </div>
    </form>

    <?php if (!$isCreate): ?>
        <section class="admin-panel">
            <h2 class="admin-panel__title">История версий</h2>
            <?php if (($versions ?? []) === []): ?>
                <p class="admin-muted">Версий пока нет.</p>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Версия</th><th>Статус</th><th>Создана</th><th>Опубликована</th></tr></thead>
                        <tbody>
                        <?php foreach ($versions as $version): ?>
                            <tr>
                                <td>v<?= text($version['version'] ?? '') ?></td>
                                <td><?= text($version['status'] ?? '') ?></td>
                                <td><?= text($version['created_at'] ?? '') ?></td>
                                <td><?= text($version['published_at'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive, 'admin_scripts' => ['/assets/code-formatter.js']]) ?>
