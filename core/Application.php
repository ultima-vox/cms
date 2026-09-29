<?php

declare(strict_types=1);

namespace Core;

use Core\Bootstrap\BuiltinRoutes;
use Core\Bootstrap\RuntimeFactory;
use Core\Controller\AdminController;
use Core\Controller\AuthController;
use Core\Controller\HealthController;
use Core\Controller\LayoutController;
use Core\Controller\NodeController;
use Core\Controller\StructureController;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Http\Request;
use Core\Layout\LayoutTemplateService;
use Core\Repository\InfosystemRepository;
use Core\Repository\LayoutRepository;
use Core\Repository\NodeRepository;
use Core\Repository\StructureRepository;
use Core\Security\SecurityHeaders;
use Core\View\FrontendRenderer;
use Core\View\PhpRenderer;

final class Application
{
    public function __construct(private readonly string $rootPath)
    {
    }

    public function run(Request $request): void
    {
        $this->startSession();

        $db = Database::connection();
        $runtime = (new RuntimeFactory())->create($db, $this->rootPath);
        $core = new ExtensionCore($runtime);
        $twig = $runtime->adminRenderer();
        $auth = $runtime->auth();
        $audit = $runtime->audit();
        $frontend = new FrontendRenderer(
            $twig,
            new PhpRenderer($this->rootPath, $core),
        );

        $builtinRoutes = new BuiltinRoutes(
            new AuthController($auth, $twig),
            new AdminController($auth, $twig),
            new StructureController(
                $auth,
                new StructureRepository($db),
                $audit,
                $twig,
            ),
            new LayoutController(
                $auth,
                new LayoutRepository($db),
                new LayoutTemplateService($this->rootPath),
                $audit,
                $twig,
            ),
            new HealthController($db),
            new NodeController(
                new NodeRepository($db),
                new InfosystemRepository($db),
                $frontend,
            ),
        );

        $builtinRoutes->register($core->routes());
        (new ModuleLoader($this->rootPath))->load($core);
        $builtinRoutes->registerPublicFallback($core->routes());
        $core->freeze();

        $router = new Router($core->routes(), $this->rootPath);
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
