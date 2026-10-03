<?php

declare(strict_types=1);

use Core\Site\SiteContext;
use Core\View\AdminPhpRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

$rootPath = dirname(__DIR__);
$coreTemplate = $rootPath . '/templates/admin/infosystems/index.php';
$moduleTemplates = $rootPath . '/modules/infosystem/templates';

if (is_file($coreTemplate)) {
    throw new RuntimeException('Infosystem admin template must not be owned by core templates.');
}

$site = new SiteContext(1, 'default', 'Default site', 'localhost');
$renderer = new AdminPhpRenderer($rootPath, [$moduleTemplates]);
$renderer->addGlobal('admin_shell', new class ($site) {
    public function __construct(private readonly SiteContext $site)
    {
    }

    public function user(): ?array
    {
        return null;
    }

    public function navigation(): array
    {
        return [];
    }

    public function site(): SiteContext
    {
        return $this->site;
    }

    public function sites(): array
    {
        return [$this->site];
    }

    public function csrfToken(): string
    {
        return 'test-csrf';
    }
});

$html = $renderer->render('admin/infosystems/index.php', [
    'deleted' => false,
    'infosystems' => [],
]);

if (!str_contains($html, 'Инфосистемы — Ultima Vox CMS')
    || !str_contains($html, 'Инфосистем пока нет')
    || !str_contains($html, 'Поиск и команды')) {
    throw new RuntimeException('Module-owned infosystem template did not render through the shared admin shell.');
}

fwrite(STDOUT, "MODULE TEMPLATE SCOPE OK\n");
