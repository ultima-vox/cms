<?php

declare(strict_types=1);

$admin_active = 'layouts';
$admin_title = 'Макеты';
$admin_styles = ['/assets/layouts.css'];
ob_start();
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow">Presentation</p><h1 class="admin-header__title">Макеты</h1><p class="admin-page-header__description">Обычные HTML/PHP-макеты без XML/XSLT и отдельного шаблонного языка публичного сайта.</p></div><a class="admin-button admin-button--primary" href="/admin/layouts/create">Новый макет</a></header>
    <?php if (!empty($deleted)): ?><div class="admin-notice admin-notice--success">Макет удалён.</div><?php endif; ?>
    <section class="admin-panel admin-panel--flush">
        <?php if ($layouts === []): ?><div class="admin-empty"><h2 class="admin-panel__title">Макетов пока нет</h2><p>Создайте первый HTML/PHP-макет для страниц сайта.</p></div><?php else: ?>
            <div class="layout-list"><div class="layout-list__head"><span>Макет</span><span>Файл</span><span>Узлы</span><span>Тип</span><span></span></div>
            <?php foreach ($layouts as $layout): ?><div class="layout-row"><div><strong><?= text($layout['name']) ?></strong><?php if (($layout['description'] ?? '') !== ''): ?><p class="layout-row__description"><?= text($layout['description']) ?></p><?php endif; ?></div><code><?= text($layout['template_path']) ?></code><span><?= (int) $layout['node_count'] ?></span><span><?php if (!empty($layout['is_system'])): ?><span class="admin-badge">системный</span><?php else: ?>пользовательский<?php endif; ?></span><a class="admin-link" href="/admin/layouts/<?= (int) $layout['id'] ?>/edit">Изменить</a></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$admin_content = (string) ob_get_clean();
require dirname(__DIR__) . '/_shell.php';
