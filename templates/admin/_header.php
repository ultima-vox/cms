<?php

declare(strict_types=1);

use Core\View\AdminShellContext;

/** @var AdminShellContext $admin_shell */
$shellUser = $admin_shell->user();
$shellNavigation = $admin_shell->navigation();
$shellSections = $admin_shell->navigationSections();
$shellSite = $admin_shell->site();
$shellSites = $admin_shell->sites();
$shellCsrf = $admin_shell->csrfToken();
$shellActive = isset($admin_active) && is_string($admin_active) ? $admin_active : 'dashboard';
$shellTitle = isset($admin_title) && is_string($admin_title) ? $admin_title : 'Администрирование';
$shellStyles = isset($admin_styles) && is_array($admin_styles) ? $admin_styles : [];
$shellSection = null;
foreach ($shellSections as $section) {
    foreach ($section['items'] as $item) {
        if ($item->code === $shellActive) {
            $shellSection = $section;
            break 2;
        }
    }
}
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
    <a class="admin-skip" href="#admin-workspace">К содержимому</a>
    <header class="admin-topbar">
        <a class="admin-brand" href="/admin" aria-label="Ultima Vox CMS">
            <span class="admin-brand__mark">UV</span>
            <span class="admin-brand__name">Ultima Vox CMS</span>
        </a>
            <strong class="admin-topbar__title"><?= text($shellTitle) ?></strong>

            <?php if (count($shellSites) > 1): ?>
                <form class="admin-site-switcher" action="/admin/sites/select" method="post">
                    <input type="hidden" name="_csrf" value="<?= text($shellCsrf) ?>"><input type="hidden" name="_site_id" value="<?= text($admin_shell->site()->id) ?>">
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

            <button class="admin-search-trigger" type="button" data-command-open aria-haspopup="dialog" aria-label="Поиск и команды">
                <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-search"></use></svg>
                <span class="admin-search-trigger__label">Поиск и команды…</span>
                <span class="admin-kbd">Ctrl K</span>
            </button>

            <div class="admin-user-menu">
                <span class="admin-user-chip" title="<?= text($displayName !== '' ? $displayName . ' · ' . $email : 'Ultima Vox CMS') ?>">
                    <?= text($initials) ?>
                </span>
                <form action="/admin/logout" method="post">
                    <input type="hidden" name="_csrf" value="<?= text($shellCsrf) ?>"><input type="hidden" name="_site_id" value="<?= text($admin_shell->site()->id) ?>">
                    <button class="admin-button admin-button--secondary" type="submit" title="Выйти">
                        <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-logout"></use></svg>
                        <span>Выйти</span>
                    </button>
                </form>
            </div>
        </header>
    <details class="admin-sections" data-admin-disclosure open>
        <summary>Разделы · <?= text($shellSection['label'] ?? 'Обзор') ?></summary>
        <nav aria-label="Основная навигация">
            <ul class="admin-sections__list">
                <li><a href="/admin"<?= $shellActive === 'dashboard' ? ' aria-current="page"' : '' ?>>Обзор</a></li>
                <?php foreach ($shellSections as $section): ?>
                    <li><a href="<?= text($section['items'][0]->path) ?>"<?= $shellSection !== null && $shellSection['code'] === $section['code'] ? ' aria-current="true"' : '' ?>><?= text($section['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </details>
    <aside class="admin-shell__sidebar">
        <details class="admin-local" data-admin-disclosure open>
            <summary>Меню · <?= text($shellSection['label'] ?? 'Обзор') ?></summary>
            <p class="admin-local__title"><?= text($shellSection['label'] ?? 'Обзор') ?></p>
            <nav class="admin-nav" aria-label="Меню раздела">
                <ul>
                <?php if ($shellSection === null): ?>
                    <li><a class="admin-nav__item" href="/admin"<?= $shellActive === 'dashboard' ? ' aria-current="page"' : '' ?>>Обзор</a></li>
                <?php else: ?>
                    <?php foreach ($shellSection['items'] as $item): ?>
                        <li><a class="admin-nav__item<?= $shellActive === $item->code ? ' admin-nav__item--active' : '' ?>" href="<?= text($item->path) ?>"<?= $shellActive === $item->code ? ' aria-current="page"' : '' ?>>
                            <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-<?= text($item->icon) ?>"></use></svg>
                            <span class="admin-nav__label"><?= text($item->label) ?></span>
                        </a></li>
                    <?php endforeach; ?>
                <?php endif; ?>
                </ul>
            </nav>
        </details>
    </aside>
    <main id="admin-workspace" tabindex="-1" class="admin-shell__main admin-shell__main--dashboard">
