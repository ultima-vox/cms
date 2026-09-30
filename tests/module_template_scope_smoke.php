<?php

declare(strict_types=1);

use Core\View\TwigRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

$rootPath = dirname(__DIR__);
$coreTemplate = $rootPath . '/templates/admin/infosystems/index.twig';
$moduleTemplates = $rootPath . '/modules/infosystem/templates';

if (is_file($coreTemplate)) {
    throw new RuntimeException('Infosystem admin template must not be owned by core templates.');
}

$renderer = new TwigRenderer($rootPath, [$moduleTemplates]);
$html = $renderer->render('admin/infosystems/index.twig', [
    'deleted' => false,
    'infosystems' => [],
]);

if (!str_contains($html, 'Инфосистемы — Ultima Vox CMS')
    || !str_contains($html, 'Инфосистем пока нет.')) {
    throw new RuntimeException('Module-owned infosystem admin template was not rendered.');
}

fwrite(STDOUT, "MODULE TEMPLATE SCOPE OK\n");
