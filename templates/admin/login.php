<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — Ultima Vox CMS</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body class="admin-login">
    <main class="admin-login__panel">
        <div class="admin-brand">ULTIMA VOX</div>
        <h1 class="admin-login__title">Вход в CMS</h1>
        <?php if (!empty($error)): ?><div class="admin-alert" role="alert"><?= text($error) ?></div><?php endif; ?>
        <form class="admin-form" action="/admin/login" method="post">
            <input type="hidden" name="_csrf" value="<?= text($csrf_token) ?>">
            <label class="admin-field"><span class="admin-field__label">Email</span><input class="admin-field__input" type="email" name="email" value="<?= text($email ?? '') ?>" autocomplete="username" required></label>
            <label class="admin-field"><span class="admin-field__label">Пароль</span><input class="admin-field__input" type="password" name="password" autocomplete="current-password" required></label>
            <button class="admin-button" type="submit">Войти</button>
        </form>
    </main>
</body>
</html>
