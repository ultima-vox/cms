<?php

declare(strict_types=1);

use Core\View\AdminShellContext;

/** @var AdminShellContext $admin_shell */
$shellNavigation = $admin_shell->navigation();
$shellActive = isset($admin_active) && is_string($admin_active) ? $admin_active : 'dashboard';
$shellScripts = isset($admin_scripts) && is_array($admin_scripts) ? $admin_scripts : [];
?>
    </main>

    <dialog class="admin-command" id="admin-command-palette" aria-label="Поиск и команды">
        <div class="admin-command__search">
            <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-search"></use></svg>
            <input class="admin-command__input" type="search" placeholder="Найти раздел или команду…" autocomplete="off" data-command-input>
            <span class="admin-kbd">Esc</span>
        </div>
        <div class="admin-command__body">
            <div class="admin-command__label">Навигация</div>
            <a class="admin-command__item<?= $shellActive === 'dashboard' ? ' admin-command__item--selected' : '' ?>" href="/admin" data-command-item data-command-keywords="dashboard overview home обзор главная">
                <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-home"></use></svg>
                <span>Обзор</span>
                <span class="admin-command__item-meta">Dashboard</span>
            </a>
            <?php foreach ($shellNavigation as $item): ?>
                <a class="admin-command__item<?= $shellActive === $item->code ? ' admin-command__item--selected' : '' ?>" href="<?= text($item->path) ?>" data-command-item data-command-keywords="<?= text($item->code . ' ' . $item->label) ?>">
                    <svg class="admin-icon" aria-hidden="true"><use href="/assets/admin-icons.svg#icon-<?= text($item->icon) ?>"></use></svg>
                    <span><?= text($item->label) ?></span>
                    <span class="admin-command__item-meta"><?= text($item->code) ?></span>
                </a>
            <?php endforeach; ?>
            <div class="admin-command__empty" data-command-empty>Ничего не найдено.</div>
        </div>
        <div class="admin-command__footer">
            <span class="admin-command__hint"><span class="admin-kbd">↑↓</span> выбрать</span>
            <span class="admin-command__hint"><span class="admin-kbd">Enter</span> открыть</span>
            <span class="admin-command__hint"><span class="admin-kbd">Esc</span> закрыть</span>
        </div>
    </dialog>

    <script src="/assets/admin.js" defer></script>
    <?php foreach ($shellScripts as $script): ?>
        <?php if (is_string($script) && $script !== ''): ?><script src="<?= text($script) ?>" defer></script><?php endif; ?>
    <?php endforeach; ?>
</body>
</html>
