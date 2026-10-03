<?php

declare(strict_types=1);

$adminActive = 'infosystems';
$infosystemId = (int) ($infosystem['id'] ?? 0);
echo $view->render('admin/_header.php', [
    'admin_active' => $adminActive,
    'admin_title' => 'Разделы сайта — ' . (string) ($infosystem['name'] ?? ''),
    'admin_styles' => ['/assets/infosystems.css'],
]);
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow"><?= text($infosystem['code'] ?? '') ?></p><h1 class="admin-header__title">Связанные разделы сайта</h1><p class="admin-page-header__description">Выберите узлы структуры, в которых эта инфосистема является основной.</p></div><div class="admin-actions"><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>/edit">Настройки</a><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>">К контенту</a></div></header>
    <?php if (!empty($saved)): ?><div class="admin-notice">Привязки сохранены.</div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="admin-notice admin-notice--error"><?= text($error) ?></div><?php endif; ?>
    <form class="admin-form" method="post" action="/admin/infosystems/<?= text($infosystemId) ?>/bindings">
        <input type="hidden" name="_csrf" value="<?= text($csrf_token ?? '') ?>">
        <section class="admin-panel"><h2 class="admin-panel__title">Структура сайта</h2><p class="admin-field__hint">Один раздел может иметь только одну основную привязку модуля <code>infosystem</code>. Переназначение раздела автоматически заменит предыдущую привязку.</p>
            <?php if (($nodes ?? []) === []): ?><div class="admin-empty">В структуре сайта пока нет разделов.</div><?php else: ?><div class="admin-form__grid">
                <?php foreach ($nodes as $node): ?><label class="admin-check admin-field--wide"><input type="checkbox" name="node_ids[]" value="<?= text($node['id'] ?? '') ?>"<?= !empty($bound_node_ids[$node['id'] ?? 0]) ? ' checked' : '' ?>><span style="padding-left: <?= text((int) ($node['depth'] ?? 0) * 18) ?>px"><code><?= text($node['path'] ?? '') ?></code> — <?= text($node['name'] ?? '') ?></span></label><?php endforeach; ?>
            </div><?php endif; ?>
        </section>
        <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit">Сохранить привязки</button><a class="admin-button" href="/admin/infosystems/<?= text($infosystemId) ?>/edit">Отмена</a></div>
    </form>
</div>
<?= $view->render('admin/_footer.php', ['admin_active' => $adminActive]) ?>
