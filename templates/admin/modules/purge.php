<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Удаление данных <?= text($module['code']) ?> — Ultima Vox CMS</title><link rel="stylesheet" href="/assets/admin.css"><link rel="stylesheet" href="/assets/modules.css"></head>
<body class="admin-shell"><aside class="admin-shell__sidebar"><div class="admin-brand">ULTIMA VOX</div><nav class="admin-nav" aria-label="Основная навигация"><a class="admin-nav__item" href="/admin">Обзор</a><a class="admin-nav__item" href="/admin/modules">Модули</a></nav></aside>
<main class="admin-shell__main"><header class="admin-page-header"><div><p class="admin-eyebrow">Destructive operation</p><h1 class="admin-header__title">Полностью удалить <?= text($module['name']) ?></h1><p class="admin-page-header__description">Эта операция удалит код пакета и данные, которые сам модуль объявил в purge-контракте. Откат после успешного commit не предусмотрен.</p></div><a class="admin-button" href="/admin/modules">Отмена</a></header>
<section class="admin-panel module-purge-panel"><div class="module-purge-warning"><strong><?= text($module['code']) ?> <?= text($module['version']) ?></strong><p>Будут выполнены destructive SQL scripts:</p><ul><?php foreach ($module['purge_scripts'] as $script): ?><li><code><?= text($script) ?></code></li><?php endforeach; ?></ul></div><form class="module-purge-form" method="post" action="/admin/modules/<?= text($module['code']) ?>/purge" autocomplete="off"><input type="hidden" name="_csrf" value="<?= text($csrf_token) ?>"><label><span>Для подтверждения введите точно: <code><?= text($module['code']) ?></code></span><input type="text" name="confirmation" required spellcheck="false" autocomplete="off"></label><button class="admin-button admin-button--danger" type="submit">Необратимо удалить модуль и его данные</button></form></section>
</main></body></html>
