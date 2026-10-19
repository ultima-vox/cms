<?php declare(strict_types=1); ?>
<section class="admin-panel admin-folder-browser" aria-label="Папки">
    <div class="admin-actions"><a class="admin-link" href="<?= text($browser['path']) ?>">Корневая папка</a><a class="admin-link" href="<?= text($browser['path']) ?>?folder=all">Все материалы</a>
        <?php foreach ($browser['ancestors'] as $ancestor): ?><a class="admin-link" href="<?= text($browser['path']) ?>?folder=<?= text($ancestor['id']) ?>"><?= text($ancestor['name']) ?></a><?php endforeach; ?>
        <?php if ($browser['current'] !== null): ?><strong><?= text($browser['current']['name']) ?></strong><a class="admin-link" href="/admin/folders/<?= text($browser['scope']) ?>/<?= text($browser['selected']) ?>/edit">Настройки папки</a><?php endif; ?>
    </div>
    <ul class="admin-folder-children"><?php foreach ($browser['children'] as $child): ?><li><a class="admin-button admin-button--secondary" href="<?= text($browser['path']) ?>?folder=<?= text($child['id']) ?>"><?= text($child['name']) ?></a></li><?php endforeach; ?></ul>
    <details class="admin-folder-create"><summary class="admin-button admin-button--secondary">Новая папка</summary>
    <form class="admin-form admin-folder-create__form" method="post" action="/admin/folders/<?= text($browser['scope']) ?>">
        <input type="hidden" name="_csrf" value="<?= text($admin_shell->csrfToken()) ?>"><input type="hidden" name="_site_id" value="<?= text($admin_shell->site()->id) ?>"><input type="hidden" name="parent_id" value="<?= text($browser['selected'] ?? '') ?>">
        <label class="admin-field"><span>Название папки<?= $browser['current'] !== null ? ' в «' . text($browser['current']['name']) . '»' : '' ?></span><input class="admin-input" name="name" required maxlength="160"></label><button class="admin-button" type="submit">Создать папку</button>
    </form>
    </details>
</section>
