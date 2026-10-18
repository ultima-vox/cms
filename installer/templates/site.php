<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Сайт и администратор — Ultima Vox CMS</title>
    <link rel="stylesheet" href="/assets/installer.css">
</head>
<body class="installer-shell">
<main class="installer-card">
    <header class="installer-header">
        <span class="installer-brand">ULTIMA VOX CMS</span>
        <p class="installer-step">Шаг 3 · Сайт и администратор</p>
        <h1>Создать сайт</h1>
        <p>В отдельной пустой базе будут созданы схема CMS, администратор и главная страница. Существующая база не перезаписывается.</p>
    </header>
    <form class="installer-form" method="post" action="/install/site">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($error !== null): ?><div class="installer-result is-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <div class="installer-form__grid">
            <label><span>Название сайта</span><input name="site_name" maxlength="255" required></label>
            <label><span>Полный URL сайта</span><input id="site-url" name="site_url" type="url" placeholder="https://cms.example.ru" required><small>Адрес определяется автоматически. При необходимости исправьте его; подпапки не поддерживаются.</small></label>
            <label class="installer-form__wide"><span>Email администратора</span><input name="email" type="email" autocomplete="username" required></label>
            <label><span>Пароль администратора</span><input name="admin_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <label><span>Повторите пароль</span><input name="admin_password_confirm" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
        </div>
        <footer class="installer-footer installer-footer--form">
            <a href="/install/database">Назад</a><button type="submit">Установить CMS</button>
        </footer>
    </form>
</main>
<script>
    const siteUrl = document.getElementById('site-url');
    if (siteUrl && siteUrl.value === '') {
        siteUrl.value = window.location.origin;
    }
</script>
</body>
</html>
