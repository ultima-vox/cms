<?php

declare(strict_types=1);

/** @var Core\View\AdminShellContext $admin_shell */
$shellUser = $admin_shell->user();
$shellNavigation = $admin_shell->navigation();
$shellSite = $admin_shell->site();
$shellSites = $admin_shell->sites();
$shellCsrf = $admin_shell->csrfToken();
$admin_active ??= 'dashboard';
$admin_title ??= 'Администрирование';
$admin_styles ??= [];
$admin_scripts ??= [];
$admin_content ??= '';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= text($admin_title) ?> — Ultima Vox CMS</title>
    <link rel="stylesheet" href="/assets/admin.css">
    <?php foreach ($admin_styles as $stylesheet): ?>
        <link rel="stylesheet" href="<?= text($stylesheet) ?>">
    <?php endforeach; ?>
</head>
<body class="admin-shell">
    <aside class="admin-shell__sidebar">
        <a class="admin-brand" href="/admin" aria-label="Ultima Vox CMS">
            <span class="admin-brand__mark">UV</span>
            <span class="admin-brand__name">Ultima Vox CMS</span>
        </a>
        <nav class="admin-nav" aria-label="Основная навигация">
            <a class="admin-nav__item<?= $admin_active === 'dashboard' ? ' admin-nav__item--active' : '' ?>" href="/admin"<?= $admin_active === 'dashboard' ? ' aria-current="page"' : '' ?>>
                <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-home"></use></svg><span class="admin-nav__label">Обзор</span>
            </a>
            <?php foreach ($shellNavigation as $item): ?>
                <a class="admin-nav__item<?= $admin_active === $item->code ? ' admin-nav__item--active' : '' ?>" href="<?= text($item->path) ?>"<?= $admin_active === $item->code ? ' aria-current="page"' : '' ?>>
                    <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-<?= text($item->icon) ?>"></use></svg><span class="admin-nav__label"><?= text($item->label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar__footer"><button class="admin-sidebar__button" type="button" data-admin-collapse aria-pressed="false"><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-collapse"></use></svg><span class="admin-sidebar__label">Свернуть</span></button></div>
    </aside>
    <main class="admin-shell__main admin-shell__main--dashboard">
        <header class="admin-topbar">
            <strong class="admin-topbar__title"><?= text($admin_title) ?></strong>
            <?php if (count($shellSites) > 1): ?>
                <form class="admin-site-switcher" action="/admin/sites/select" method="post">
                    <input type="hidden" name="_csrf" value="<?= text($shellCsrf) ?>">
                    <select class="admin-site-switcher__select" name="site_id" data-site-select aria-label="Текущий сайт">
                        <?php foreach ($shellSites as $site): ?><option value="<?= $site->id ?>"<?= $site->id === $shellSite->id ? ' selected' : '' ?>><?= text($site->name) ?><?= $site->host !== '' ? ' · ' . text($site->host) : '' ?></option><?php endforeach; ?>
                    </select>
                </form>
            <?php else: ?>
                <div class="admin-site-switcher"><div class="admin-site-switcher__select"><?= text($shellSite->name) ?><?= $shellSite->host !== '' ? ' · ' . text($shellSite->host) : '' ?></div></div>
            <?php endif; ?>
            <button class="admin-search-trigger" type="button" data-command-open aria-haspopup="dialog"><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-search"></use></svg><span class="admin-search-trigger__label">Поиск и команды…</span><span class="admin-kbd">Ctrl K</span></button>
            <div class="admin-user-menu">
                <span class="admin-user-chip" title="<?= $shellUser !== null ? text(($shellUser['display_name'] ?? '') . ' · ' . ($shellUser['email'] ?? '')) : 'Ultima Vox CMS' ?>"><?= $shellUser !== null ? text(mb_strtoupper(mb_substr((string) ($shellUser['display_name'] ?? ''), 0, 2))) : 'UV' ?></span>
                <form action="/admin/logout" method="post"><input type="hidden" name="_csrf" value="<?= text($shellCsrf) ?>"><button class="admin-button admin-button--secondary" type="submit" title="Выйти"><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-logout"></use></svg><span>Выйти</span></button></form>
            </div>
        </header>
        <?= $admin_content ?>
    </main>
    <dialog class="admin-command" id="admin-command-palette" aria-label="Поиск и команды">
        <div class="admin-command__search"><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-search"></use></svg><input class="admin-command__input" type="search" placeholder="Найти раздел или команду…" autocomplete="off" data-command-input><span class="admin-kbd">Esc</span></div>
        <div class="admin-command__body"><div class="admin-command__label">Навигация</div>
            <a class="admin-command__item<?= $admin_active === 'dashboard' ? ' admin-command__item--selected' : '' ?>" href="/admin" data-command-item data-command-keywords="dashboard overview home обзор главная"><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-home"></use></svg><span>Обзор</span><span class="admin-command__item-meta">Dashboard</span></a>
            <?php foreach ($shellNavigation as $item): ?><a class="admin-command__item<?= $admin_active === $item->code ? ' admin-command__item--selected' : '' ?>" href="<?= text($item->path) ?>" data-command-item data-command-keywords="<?= text($item->code . ' ' . $item->label) ?>"><svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-<?= text($item->icon) ?>"></use></svg><span><?= text($item->label) ?></span><span class="admin-command__item-meta"><?= text($item->code) ?></span></a><?php endforeach; ?>
            <div class="admin-command__empty" data-command-empty>Ничего не найдено.</div>
        </div>
        <div class="admin-command__footer"><span class="admin-command__hint"><span class="admin-kbd">↑↓</span> выбрать</span><span class="admin-command__hint"><span class="admin-kbd">Enter</span> открыть</span><span class="admin-command__hint"><span class="admin-kbd">Esc</span> закрыть</span></div>
    </dialog>
    <script src="/assets/admin.js" defer></script>
    <?php foreach ($admin_scripts as $script): ?><script src="<?= text($script) ?>" defer></script><?php endforeach; ?>
</body>
</html>
