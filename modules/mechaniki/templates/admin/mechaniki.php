<?php

declare(strict_types=1);
echo $view->render('admin/_header.php', ['admin_active' => 'mechaniki', 'admin_title' => 'Механики · MVP']);
?>
<div class="admin-dashboard">
    <header class="admin-page-header"><div><p class="admin-eyebrow">Первый рабочий сайт</p><h1 class="admin-header__title">Механики · MVP</h1><p>Новый интерфейс автосервиса на структуре и документах CMS.</p></div></header>
    <?php if ($error !== null): ?><div class="admin-notice"><?= text($error) ?></div><?php endif; ?>
    <?php if ($created): ?><div class="admin-notice admin-notice--success">Страницы созданы. Откройте сайт и проверьте редактирование.</div><?php endif; ?>
    <section class="admin-panel">
        <h2 class="admin-panel__title">Загрузить пилотный сайт</h2>
        <p>Будет создан раздел <strong>/mechaniki</strong>: главная, услуги, ремонт двигателя, подвески, о сервисе и контакты. Существующие страницы сохраняются. Повторная загрузка блокируется.</p>
        <p>Тексты редактируются в «Документах», адреса и заголовки — в «Структуре», общий шаблон — в «Макетах». Запись в этой версии — по телефону; форма заявок не подключена.</p>
        <form method="post" action="/admin/mechaniki/import"><input type="hidden" name="_csrf" value="<?= text($admin_shell->csrfToken()) ?>"><input type="hidden" name="_site_id" value="<?= text($admin_shell->site()->id) ?>"><button class="admin-button admin-button--primary" type="submit">Создать страницы «Механиков»</button> <a class="admin-button" href="/mechaniki" target="_blank" rel="noopener">Открыть сайт ↗</a></form>
    </section>
</div>
<?= $view->render('admin/_footer.php') ?>
