<?php

declare(strict_types=1);

$admin_active = 'structure';
$admin_title = 'Структура';
$admin_styles = ['/assets/structure.css'];
$admin_scripts = ['/assets/structure.js'];
ob_start();
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow">Content</p><h1 class="admin-header__title">Структура сайта</h1><p class="admin-page-header__description">Узлы определяют URL, макет и связанный источник контента.</p></div><a class="admin-button admin-button--primary" href="/admin/structure/create">Новый узел</a></header>
    <?php if (!empty($saved)): ?><div class="admin-notice admin-notice--success">Изменения сохранены.</div><?php endif; ?>
    <?php if (!empty($deleted)): ?><div class="admin-notice admin-notice--success">Узел удалён.</div><?php endif; ?>
    <section class="admin-panel admin-panel--flush">
        <?php if ($nodes === []): ?><div class="admin-empty"><h2 class="admin-panel__title">Структура пока пуста</h2><p>Создайте корневой узел. Его URL всегда <code>/</code>.</p><a class="admin-button admin-button--primary" href="/admin/structure/create">Создать корневой узел</a></div><?php else: ?>
            <div class="structure-list" id="structure-list" data-csrf="<?= text($csrf_token) ?>"><div class="structure-list__head"><span>Страница</span><span>URL</span><span>Статус</span><span>Макет</span><span></span></div>
            <?php foreach ($nodes as $node): ?><div class="structure-row" draggable="true" data-id="<?= (int) $node['id'] ?>" data-parent="<?= text($node['parent_id'] ?? '') ?>" data-sorting="<?= (int) $node['sorting'] ?>"><div class="structure-row__name" style="--tree-depth:<?= (int) $node['depth'] ?>"><span class="structure-row__drag" title="Перетащить">⋮⋮</span><span><strong><?= text($node['name']) ?></strong><?php if (empty($node['is_active'])): ?> <span class="admin-badge">выключен</span><?php endif; ?></span></div><code class="structure-row__path"><?= text($node['path']) ?></code><span class="admin-status admin-status--<?= text($node['status']) ?>"><?= text($node['status']) ?></span><span class="structure-row__muted"><?= text($node['layout_name'] ?? '—') ?></span><a class="admin-link" href="/admin/structure/<?= (int) $node['id'] ?>/edit">Изменить</a></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$admin_content = (string) ob_get_clean();
require dirname(__DIR__) . '/_shell.php';
