<?php

declare(strict_types=1);

/** @var list<array{code:string,label:string,ok:bool,required:bool,details:string}> $checks */
/** @var bool $requiredPassed */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Установка — Ultima Vox CMS</title>
    <link rel="stylesheet" href="/assets/installer.css">
</head>
<body class="installer-shell">
    <main class="installer-card">
        <header class="installer-header">
            <span class="installer-brand">ULTIMA VOX CMS</span>
            <p class="installer-step">Шаг 1 · Проверка сервера</p>
            <h1>Подготовка к установке</h1>
            <p>Проверяем только обязательные возможности обычного PHP-сервера. Redis, Node, Docker и фоновые workers для базовой установки не требуются.</p>
        </header>

        <div class="installer-checks">
            <?php foreach ($checks as $check): ?>
                <div class="installer-check">
                    <span class="installer-check__status <?= $check['ok'] ? 'is-ok' : ($check['required'] ? 'is-error' : 'is-warning') ?>">
                        <?= $check['ok'] ? 'OK' : ($check['required'] ? 'Ошибка' : 'Опционально') ?>
                    </span>
                    <div>
                        <strong><?= htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <p><?= htmlspecialchars($check['details'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <footer class="installer-footer">
            <?php if ($requiredPassed): ?>
                <div class="installer-result is-ok">Обязательные проверки пройдены. Сервер готов к следующему шагу установки.</div>
                <button type="button" disabled>Настройка базы данных — следующий этап</button>
            <?php else: ?>
                <div class="installer-result is-error">Исправьте обязательные ошибки сервера и обновите страницу.</div>
                <a href="/install">Проверить снова</a>
            <?php endif; ?>
        </footer>
    </main>
</body>
</html>
