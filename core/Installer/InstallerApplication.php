<?php

declare(strict_types=1);

namespace Core\Installer;

use Core\Http\Request;
use Core\Http\Response;

final readonly class InstallerApplication
{
    public function __construct(private string $rootPath)
    {
    }

    public function run(Request $request): void
    {
        if ($request->path !== '/install' && $request->path !== '/install/') {
            Response::redirect('/install')->send();
            return;
        }

        $checker = new EnvironmentChecker($this->rootPath);
        $checks = $checker->checks();
        $html = $this->render('check.php', [
            'checks' => $checks,
            'requiredPassed' => $checker->requiredPassed($checks),
        ]);

        Response::html($html)->send();
    }

    /** @param array<string, mixed> $data */
    private function render(string $template, array $data): string
    {
        $path = $this->rootPath . '/installer/templates/' . $template;
        if (!is_file($path)) {
            throw new \RuntimeException('Installer template not found.');
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $path;
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
