<?php

declare(strict_types=1);

namespace Core;

use Core\Controller\AdminController;
use Core\Controller\AuthController;
use Core\Controller\HealthController;
use Core\Controller\NodeController;
use Core\Http\Request;
use Core\Repository\InfosystemRepository;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\NodeRepository;
use Core\Repository\UserRepository;
use Core\Security\AuthService;
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
        $auth = new AuthService(
            new UserRepository($db),
            new LoginAttemptRepository($db),
        );

        $router = new Router(
            new AuthController($auth, $view),
            new AdminController($auth, $view),
            new HealthController($db),
            new NodeController(
                new NodeRepository($db),
                new InfosystemRepository($db),
                $view,
            ),
            $this->rootPath,
        );

        $response = SecurityHeaders::apply($router->dispatch($request));

        if ($this->isHttps()) {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        $response->send();
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_name('uvcms_session');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => $this->isHttps(),
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }

    private function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
