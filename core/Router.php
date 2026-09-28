<?php

declare(strict_types=1);

namespace Core;

use Core\Controller\AdminController;
use Core\Controller\AuthController;
use Core\Controller\HealthController;
use Core\Controller\NodeController;
use Core\Http\Request;
use Core\Http\Response;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use RuntimeException;

use function FastRoute\cachedDispatcher;

final class Router
{
    public function __construct(
        private readonly AuthController $authController,
        private readonly AdminController $adminController,
        private readonly HealthController $healthController,
        private readonly NodeController $nodeController,
        private readonly string $rootPath,
    ) {
    }

    public function dispatch(Request $request): Response
    {
        $dispatcher = cachedDispatcher(
            static function (RouteCollector $router): void {
                $router->addRoute('GET', '/health', 'health.index');
                $router->addRoute('GET', '/admin/login', 'auth.form');
                $router->addRoute('POST', '/admin/login', 'auth.login');
                $router->addRoute('POST', '/admin/logout', 'auth.logout');
                $router->addRoute('GET', '/admin', 'admin.index');
                $router->addRoute('GET', '/', 'node.resolve');
                $router->addRoute('GET', '/{path:.+}', 'node.resolve');
            },
            [
                'cacheFile' => $this->rootPath . '/storage/cache/routes.php',
                'cacheDisabled' => Config::environment() !== 'production',
            ],
        );

        $routeInfo = $dispatcher->dispatch($request->method, $request->path);

        if ($routeInfo[0] === Dispatcher::NOT_FOUND) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            return Response::html('<h1>405 Method Not Allowed</h1>', 405)
                ->withHeader('Allow', implode(', ', $routeInfo[1]));
        }

        $routeId = $routeInfo[1];
        $variables = $routeInfo[2] ?? [];

        if (!is_string($routeId)) {
            throw new RuntimeException('Маршрут содержит некорректный идентификатор обработчика.');
        }

        $handler = $this->resolveHandler($routeId);
        $response = $handler($request, $variables);

        if (!$response instanceof Response) {
            throw new RuntimeException('Контроллер должен вернуть Core\\Http\\Response.');
        }

        return $response;
    }

    private function resolveHandler(string $routeId): callable
    {
        return match ($routeId) {
            'health.index' => [$this->healthController, 'index'],
            'auth.form' => [$this->authController, 'form'],
            'auth.login' => [$this->authController, 'login'],
            'auth.logout' => [$this->authController, 'logout'],
            'admin.index' => [$this->adminController, 'index'],
            'node.resolve' => [$this->nodeController, 'resolve'],
            default => throw new RuntimeException(sprintf('Неизвестный route ID: %s.', $routeId)),
        };
    }
}
