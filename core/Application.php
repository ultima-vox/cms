<?php

declare(strict_types=1);

namespace Core;

use Core\Controller\AdminController;
use Core\Controller\HealthController;
use Core\Controller\NodeController;
use Core\Http\Request;
use Core\Repository\InfosystemRepository;
use Core\Repository\NodeRepository;
use Core\Security\SecurityHeaders;
use Core\View\TwigRenderer;

final class Application
{
    public function __construct(private readonly string $rootPath)
    {
    }

    public function run(Request $request): void
    {
        $this->startSession();

        $db = Database::connection();
        $view = new TwigRenderer($this->rootPath);

        $router = new Router(
            new AdminController($view),
            new HealthController($db),
            new NodeController(
                new NodeRepository($db),
                new InfosystemRepository($db),
                $view,
            ),
        );

        SecurityHeaders::apply($router->dispatch($request))->send();
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_name('uvcms_session');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => $https,
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }
}
