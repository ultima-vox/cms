<?php

declare(strict_types=1);

use Core\View\AdminShellContext;

/** @var AdminShellContext $admin_shell */
$shellUser = $admin_shell->user();
$shellNavigation = $admin_shell->navigation();
$shellSite = $admin_shell->site();
$shellSites = $admin_shell->sites();
$shellCsrf = $admin_shell->csrfToken();
$shellActive = isset($admin_active) && is_string($admin_active) ? $admin_active : 'dashboard';
$shellTitle = isset($admin_title) && is_string($admin_title) ? $admin_title : 'Администрирование';
$shellStyles = isset($admin_styles) && is_array($admin_styles) ? $admin_styles : [];
$displayName = is_array($shellUser) ? (string) ($shellUser['display_name'] ?? '') : '';
$email = is_array($shellUser) ? (string) ($shellUser['email'] ?? '') : '';
$initials = $displayName !== '' ? mb_strtoupper(mb_substr($displayName, 0, 2)) : 'UV';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= text($shellTitle) ?> — Ultima Vox CMS</title>
    <link rel="stylesheet" href="/assets/admin.css">
    <?php foreach ($shellStyles as $style): ?>
        <?php if (is_string($style) && $style !== ''): ?><link rel="stylesheet" href="<?= text($style) ?>"><?php endif; ?>
    <?php endforeach; ?>
</head>
<body class="admin-shell">
    <aside class="admin-shell__sidebar">
        <a class="admin-brand" href="/admin" aria-label="Ultima Vox CMS">
            <span class="admin-brand__mark">UV</span>
            <span class="admin-brand__name">Ultima Vox CMS</span>
        </a>

        <nav class="admin-nav" aria-label="Основная навигация">
            <a class="admin-nav__item<?= $shellActive === 'dashboard' ? ' admin-nav__item--active' : '' ?>" href="/admin"<?= $shellActive === 'dashboard' ? ' aria-current="page"' : '' ?>>
                <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-home"></use></svg>
                <span class="admin-nav__label">Обзор</span>
            </a>
            <?php foreach ($shellNavigation as $item): ?>
                <?php $active = $shellActive === $item->code; ?>
                <a class="admin-nav__item<?= $active ? ' admin-nav__item--active' : '' ?>" href="<?= text($item->path) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                    <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-<?= text($item->icon) ?>"></use></svg>
                    <span class="admin-nav__label"><?= text($item->label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar__footer">
            <button class="admin-sidebar__button" type="button" data-admin-collapse aria-pressed="false">
                <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-collapse"></use></svg>
                <span class="admin-sidebar__label">Свернуть</span>
            </button>
        </div>
    </aside>

    <main class="admin-shell__main admin-shell__main--dashboard">
        <header class="admin-topbar">
            <strong class="admin-topbar__title"><?= text($shellTitle) ?></strong>

            <?php if (count($shellSites) > 1): ?>
                <form class="admin-site-switcher" action="/admin/sites/select" method="post">
                    <input type="hidden" name="_csrf" value="<?= text($shellCsrf) ?>">
                    <select class="admin-site-switcher__select" name="site_id" data-site-select aria-label="Текущий сайт">
                        <?php foreach ($shellSites as $site): ?>
                            <option value="<?= text($site->id) ?>"<?= $site->id === $shellSite->id ? ' selected' : '' ?>>
                                <?= text($site->name) ?><?= $site->host !== '' ? ' · ' . text($site->host) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php else: ?>
                <div class="admin-site-switcher">
                    <div class="admin-site-switcher__select"><?= text($shellSite->name) ?><?= $shellSite->host !== '' ? ' · ' . text($shellSite->host) : '' ?></div>
                </div>
            <?php endif; ?>

            <button class="admin-search-trigger" type="button" data-command-open aria-haspopup="dialog">
                <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-search"></use></svg>
                <span class="admin-search-trigger__label">Поиск и команды…</span>
                <span class="admin-kbd">Ctrl K</span>
            </button>

            <div class="admin-user-menu">
                <span class="admin-user-chip" title="<?= text($displayName !== '' ? $displayName . ' · ' . $email : 'Ultima Vox CMS') ?>">
                    <?= text($initials) ?>
                </span>
                <form action="/admin/logout" method="post">
                    <input type="hidden" name="_csrf" value="<?= text($shellCsrf) ?>">
                    <button class="admin-button admin-button--secondary" type="submit" title="Выйти">
                        <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-logout"></use></svg>
                        <span>Выйти</span>
                    </button>
                </form>
            </div>
        </header>
