<?php

declare(strict_types=1);
$home = $page->path() === '/mechaniki';
$navigation = ['/mechaniki/services' => 'Услуги', '/mechaniki/about' => 'О сервисе', '/mechaniki/contacts' => 'Контакты'];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#14202d">
    <title><?= text($page->title()) ?> — Механики</title>
    <meta name="description" content="<?= text($page->metaDescription() ?? '') ?>">
    <link rel="stylesheet" href="/assets/mechaniki/site.css">
    <script src="/assets/mechaniki/site.js" defer></script>
</head>
<body class="mk-site">
<a class="mk-skip" href="#main">К содержимому</a>
<div class="mk-topbar"><div class="mk-wrap"><span>Зеленоград · Панфиловский проспект, 15</span><span>Пн–Пт 10:00–20:00 · Сб–Вс 10:00–19:00</span></div></div>
<header class="mk-header">
    <div class="mk-wrap mk-header-row">
        <a class="mk-brand" href="/mechaniki" aria-label="Механики — главная"><img src="/assets/mechaniki/logo.svg" width="172" height="42" alt="Механики"><span>Автосервис в Зеленограде</span></a>
        <button class="mk-menu-toggle" type="button" aria-controls="mk-navigation" aria-expanded="false">Меню <span aria-hidden="true">☰</span></button>
        <nav class="mk-nav" id="mk-navigation" aria-label="Основная навигация">
            <?php foreach ($navigation as $href => $label): ?><a href="<?= text($href) ?>"<?= $page->path() === $href ? ' aria-current="page"' : '' ?>><?= text($label) ?></a><?php endforeach; ?>
        </nav>
        <a class="mk-header-phone" href="tel:+79268150924">+7 (926) 815-09-24<span>Позвоните перед приездом</span></a>
        <a class="mk-button mk-header-cta" href="tel:+79268150924">Записаться <span aria-hidden="true">↗</span></a>
    </div>
</header>
<main id="main">
    <?php if ($home): ?>
        <section class="mk-hero mk-wrap">
            <div class="mk-hero-copy"><p class="mk-eyebrow"><span></span>Диагностика · обслуживание · ремонт</p><h1><?= text($page->title()) ?></h1><p class="mk-lead">Поможем понять, что происходит с автомобилем. Объясним причину, обсудим стоимость — и только потом приступим к работе.</p><div class="mk-actions"><a class="mk-button" href="tel:+79268150924">Обсудить неисправность <span aria-hidden="true">↗</span></a><a class="mk-text-link" href="/mechaniki/services">Выбрать услугу <span aria-hidden="true">→</span></a></div><p class="mk-hero-note">Название сломанной детали знать необязательно.</p></div>
            <figure class="mk-hero-image"><img src="/assets/mechaniki/workshop.webp" width="1440" height="960" alt="Автомобили на подъёмниках в мастерской «Механики»" fetchpriority="high"><figcaption><span class="mk-location-dot"></span>Зеленоград, Панфиловский проспект, 15</figcaption><span class="mk-image-index" aria-hidden="true">01 / В МАСТЕРСКОЙ</span></figure>
        </section>
        <div class="mk-wrap mk-principles"><div><span>01</span><strong>Сначала причина</strong><p>Начинаем с проверки</p></div><div><span>02</span><strong>Стоимость до ремонта</strong><p>Обсуждаем состав работ</p></div><div><span>03</span><strong>Решение за вами</strong><p>Работаем после согласования</p></div></div>
    <?php else: ?>
        <header class="mk-page-heading mk-wrap"><p class="mk-breadcrumb"><a href="/mechaniki">Главная</a> / <?= text($page->name()) ?></p><p class="mk-eyebrow">Автосервис «Механики»</p><h1><?= text($page->title()) ?></h1><p class="mk-lead"><?= text($page->metaDescription() ?? '') ?></p></header>
    <?php endif; ?>
    <div class="mk-content"><?= $page->execute() ?></div>
</main>
<section class="mk-contact-band"><div class="mk-wrap"><div><p class="mk-eyebrow">Давайте начнём с разговора</p><h2>Расскажите, что беспокоит автомобиль.</h2><p>Назовите модель и симптомы. Обсудим, с какой проверки начать и когда приехать.</p></div><a class="mk-button mk-button--light" href="tel:+79268150924">+7 (926) 815-09-24 <span aria-hidden="true">↗</span></a></div></section>
<footer class="mk-footer mk-wrap"><div><a class="mk-footer-brand" href="/mechaniki">Механики<span>Автосервис в Зеленограде</span></a><p>Панфиловский проспект, 15<br>Пн–Пт 10:00–20:00 · Сб–Вс 10:00–19:00</p></div><nav aria-label="Навигация в подвале"><?php foreach ($navigation as $href => $label): ?><a href="<?= text($href) ?>"><?= text($label) ?></a><?php endforeach; ?></nav><div class="mk-footer-meta"><span>© <?= date('Y') ?> Механики</span><span>Тестовая версия · Vox CMS</span></div></footer>
</body>
</html>
