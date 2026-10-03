<?php

declare(strict_types=1);

use UltimaVox\Modules\Menu\MenuNode;
use UltimaVox\Modules\Menu\MenuViewModel;

/** @var MenuViewModel $menu */

$renderItems = static function (array $items) use (&$renderItems): void {
    if ($items === []) {
        return;
    }
    ?>
    <ul class="uv-menu__list">
        <?php foreach ($items as $item): ?>
            <?php if (!$item instanceof MenuNode) continue; ?>
            <li class="uv-menu__item<?= $item->active ? ' is-active' : '' ?><?= $item->current ? ' is-current' : '' ?>">
                <a
                    class="uv-menu__link"
                    href="<?= text($item->url) ?>"
                    <?= $item->current ? 'aria-current="page"' : '' ?>
                ><?= text($item->label) ?></a>
                <?php $renderItems($item->children); ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};
?>
<nav class="uv-menu" data-menu="<?= text($menu->code) ?>" aria-label="<?= text($menu->name) ?>">
    <?php $renderItems($menu->items); ?>
</nav>
