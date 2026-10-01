<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= text($page->title()) ?></title>
    <?php if ($page->metaDescription() !== null): ?>
        <meta name="description" content="<?= text($page->metaDescription()) ?>">
    <?php endif; ?>
</head>
<body>
    <main>
        <h1><?= text($page->title()) ?></h1>

        <?= $page->execute() ?>
    </main>
</body>
</html>
