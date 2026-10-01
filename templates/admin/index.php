<?php

declare(strict_types=1);

$admin_active = 'dashboard';
$admin_title = 'Обзор';
ob_start();
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div class="admin-page-header__copy"><p class="admin-eyebrow">Workspace</p><h1 class="admin-page-title"><?= text($admin_site->name) ?></h1><p class="admin-page-description">Контент, настройки и модули выбранного сайта в одном рабочем пространстве.</p></div></header>
    <section class="admin-metrics" aria-label="Сводка">
        <article class="admin-metric"><div class="admin-metric__label"><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-globe"></use></svg>Текущий сайт</div><div class="admin-metric__value"><?= text($admin_site->code) ?></div><div class="admin-metric__meta"><?= text($admin_site->host !== '' ? $admin_site->host : 'домен не назначен') ?></div></article>
        <article class="admin-metric"><div class="admin-metric__label"><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-module"></use></svg>Разделы</div><div class="admin-metric__value"><?= count($navigation) ?></div><div class="admin-metric__meta">доступно текущему пользователю</div></article>
        <article class="admin-metric"><div class="admin-metric__label"><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-activity"></use></svg>Активность</div><div class="admin-metric__value"><?= count($activity) ?></div><div class="admin-metric__meta">последних записей журнала</div></article>
        <article class="admin-metric"><div class="admin-metric__label"><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-code"></use></svg>Ultima Vox</div><div class="admin-metric__value"><?= text($cms_version) ?></div><div class="admin-metric__meta">PHP <?= text($php_version) ?></div></article>
    </section>
    <div class="admin-dashboard-grid">
        <section class="admin-panel admin-panel--flush">
            <div class="admin-panel__header" style="padding:18px 18px 0"><div><h2 class="admin-panel__title">Последние изменения</h2><p class="admin-panel__text">Журнал административных действий.</p></div></div>
            <?php if ($activity === []): ?><div class="admin-empty"><h3 class="admin-panel__title">Изменений пока нет</h3><p>Здесь появятся последние действия редакторов и администраторов.</p></div><?php else: ?>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Действие</th><th>Сущность</th><th>Пользователь</th><th>Время</th></tr></thead><tbody>
                <?php foreach ($activity as $activityItem): ?><tr><td><span class="admin-table__primary"><?= text($activityItem['action']) ?></span></td><td><?php if ($activityItem['entity_type'] !== null): ?><span class="admin-badge admin-badge--info"><?= text($activityItem['entity_type']) ?></span><?php if ($activityItem['entity_id'] !== null): ?><span class="admin-table__secondary">#<?= (int) $activityItem['entity_id'] ?></span><?php endif; ?><?php else: ?><span class="admin-table__secondary">system</span><?php endif; ?></td><td><?= text($activityItem['display_name'] ?? 'System') ?></td><td><?= text((new DateTimeImmutable($activityItem['created_at']))->format('d.m.Y H:i')) ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
        <aside class="admin-stack">
            <section class="admin-panel"><div class="admin-panel__header"><h2 class="admin-panel__title">Система</h2></div><div class="admin-status-list"><div class="admin-status-row"><span class="admin-status-row__label">Сайт</span><span class="admin-status-row__value"><?= text($admin_site->name) ?></span></div><div class="admin-status-row"><span class="admin-status-row__label">Контекст</span><span class="admin-status-row__value">#<?= $admin_site->id ?></span></div><div class="admin-status-row"><span class="admin-status-row__label">CMS</span><span class="admin-status-row__value"><?= text($cms_version) ?></span></div><div class="admin-status-row"><span class="admin-status-row__label">PHP</span><span class="admin-status-row__value"><?= text($php_version) ?></span></div></div></section>
            <section class="admin-panel"><div class="admin-panel__header"><h2 class="admin-panel__title">Быстрый доступ</h2></div><div class="admin-quick-actions"><?php foreach ($navigation as $item): ?><a class="admin-quick-action" href="<?= text($item->path) ?>"><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-<?= text($item->icon) ?>"></use></svg><span><?= text($item->label) ?></span><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-chevron"></use></svg></a><?php endforeach; ?></div></section>
        </aside>
    </div>
</div>
<?php
$admin_content = (string) ob_get_clean();
require __DIR__ . '/_shell.php';
