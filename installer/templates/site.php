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
            <label><span>Название сайта</span><input name="site_name" value="Vox CMS" maxlength="255" required></label>
            <label><span>Полный URL сайта</span><input id="site-url" name="site_url" type="url" placeholder="https://cms.example.ru" required><small>Адрес определяется автоматически. При необходимости исправьте его; подпапки не поддерживаются.</small></label>
            <label class="installer-form__wide"><span>Email администратора</span><input name="email" type="email" autocomplete="username" required></label>
            <label><span>Пароль администратора</span><input id="admin-password" name="admin_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <label><span>Повторите пароль</span><input id="admin-password-confirm" name="admin_password_confirm" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <div id="password-tools" class="installer-form__wide" hidden>
                <div class="installer-password-actions">
                    <button id="generate-password" type="button">Сгенерировать пароль</button>
                    <button id="show-password" type="button" aria-controls="admin-password admin-password-confirm" aria-pressed="false">Показать пароль</button>
                </div>
                <small id="password-message" role="status" aria-live="polite">Можно задать свой пароль или сгенерировать случайный.</small>
            </div>
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

    const password = document.getElementById('admin-password');
    const confirmation = document.getElementById('admin-password-confirm');
    const generate = document.getElementById('generate-password');
    const show = document.getElementById('show-password');
    const message = document.getElementById('password-message');
    document.getElementById('password-tools').hidden = false;

    function showPassword(visible) {
        password.type = confirmation.type = visible ? 'text' : 'password';
        show.textContent = visible ? 'Скрыть пароль' : 'Показать пароль';
        show.setAttribute('aria-pressed', String(visible));
    }

    show.addEventListener('click', () => showPassword(password.type === 'password'));
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
        generate.addEventListener('click', () => {
            const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
            const bytes = window.crypto.getRandomValues(new Uint8Array(24));
            password.value = confirmation.value = Array.from(bytes, byte => alphabet[byte & 63]).join('');
            showPassword(true);
            message.textContent = 'Создан случайный пароль из 24 символов. Сохраните его перед установкой.';
            password.focus();
            password.select();
        });
    } else {
        generate.disabled = true;
        message.textContent = 'Генерация недоступна в этом браузере. Введите свой пароль.';
    }
</script>
</body>
</html>
