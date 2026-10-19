<?php

declare(strict_types=1);

/** @var string $csrfToken */
/** @var string|null $error */
/** @var array{host:string,port:string,database:string,user:string} $values */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>База данных — Ultima Vox CMS</title>
    <link rel="stylesheet" href="/assets/installer.css">
</head>
<body class="installer-shell">
    <main class="installer-card">
        <header class="installer-header">
            <span class="installer-brand">ULTIMA VOX CMS</span>
            <p class="installer-step">Шаг 2 · База данных</p>
            <h1>Подключение к базе данных</h1>
            <p>Параметры проверяются напрямую. Пароль не выводится обратно в HTML и пока не записывается в <code>.env</code>.</p>
        </header>

        <form class="installer-form" method="post" action="/install/database" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <?php if ($error !== null): ?>
                <div class="installer-result is-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <label><span>СУБД</span>
                <select name="driver" id="database-driver">
                    <option value="pgsql" <?= $values['driver'] === 'pgsql' ? 'selected' : '' ?>>PostgreSQL — основной, рекомендуется</option>
                    <option value="mysql" <?= $values['driver'] === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB — legacy, совместимость с хостингом</option>
                </select>
            </label>
            <p>PostgreSQL 16+ · MySQL 8.0.21+ / MariaDB 10.11+. Для выбранной СУБД нужен соответствующий PDO-драйвер.</p>
            <div class="installer-form__grid">
                <label>
                    <span>Сервер</span>
                    <input type="text" name="host" maxlength="253" required value="<?= htmlspecialchars($values['host'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    <span>Порт</span>
                    <input type="number" name="port" min="1" max="65535" required value="<?= htmlspecialchars($values['port'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    <span>База данных</span>
                    <input type="text" name="database" maxlength="120" required value="<?= htmlspecialchars($values['database'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    <span>Пользователь</span>
                    <input type="text" name="user" maxlength="120" required value="<?= htmlspecialchars($values['user'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label class="installer-form__wide">
                    <span>Пароль</span>
                    <input type="password" name="password" maxlength="1024" autocomplete="new-password">
                </label>
            </div>

            <footer class="installer-footer installer-footer--form">
                <a href="/install">Назад</a>
                <button type="submit">Проверить подключение</button>
            </footer>
        </form>
    </main>
    <script src="/assets/installer-database.js" defer></script>
</body>
</html>
