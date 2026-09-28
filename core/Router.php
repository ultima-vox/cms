<?php

declare(strict_types=1);

namespace Core;

use Core\Controller\AdminController;
use Core\Controller\AuthController;
use Core\Controller\HealthController;
use Core\Controller\LayoutController;
use Core\Controller\NodeController;
use Core\Controller\StructureController;
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
        private readonly StructureController $structureController,
        private readonly LayoutController $layoutController,
        private readonly HealthController $healthController,
        private readonly NodeController $nodeController,
        private readonly string $rootPath,
    ) {
    }

    public function dispatch(Request $request): Response
    {
        $routeCacheVersion = (string) (filemtime(__FILE__) ?: 0);

        $dispatcher = cachedDispatcher(
            static function (RouteCollector $router): void {
                $router->addRoute('GET', '/health', 'health.index');
                $router->addRoute('GET', '/admin/login', 'auth.form');
                $router->addRoute('POST', '/admin/login', 'auth.login');
                $router->addRoute('POST', '/admin/logout', 'auth.logout');
                $router->addRoute('GET', '/admin', 'admin.index');

                $router->addRoute('GET', '/admin/structure', 'structure.index');
                $router->addRoute('GET', '/admin/structure/create', 'structure.create');
                $router->addRoute('POST', '/admin/structure', 'structure.store');
                $router->addRoute('POST', '/admin/structure/reorder', 'structure.reorder');
                $router->addRoute('GET', '/admin/structure/{id:\\d+}/edit', 'structure.edit');
                $router->addRoute('POST', '/admin/structure/{id:\\d+}', 'structure.update');
                $router->addRoute('POST', '/admin/structure/{id:\\d+}/delete', 'structure.delete');

                $router->addRoute('GET', '/admin/layouts', 'layout.index');
                $router->addRoute('GET', '/admin/layouts/create', 'layout.create');
                $router->addRoute('POST', '/admin/layouts', 'layout.store');
                $router->addRoute('GET', '/admin/layouts/{id:\\d+}/edit', 'layout.edit');
                $router->addRoute('POST', '/admin/layouts/{id:\\d+}', 'layout.update');
                $router->addRoute('POST', '/admin/layouts/{id:\\d+}/reset', 'layout.reset');
                $router->addRoute('POST', '/admin/layouts/{id:\\d+}/delete', 'layout.delete');

                $router->addRoute('GET', '/', 'node.resolve');
                $router->addRoute('GET', '/{path:.+}', 'node.resolve');
            },
            [
                'cacheFile' => $this->rootPath . '/storage/cache/routes-' . $routeCacheVersion . '.php',
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
            'structure.index' => [$this->structureController, 'index'],
            'structure.create' => [$this->structureController, 'createForm'],
            'structure.store' => [$this->structureController, 'store'],
            'structure.edit' => [$this->structureController, 'editForm'],
            'structure.update' => [$this->structureController, 'update'],
            'structure.delete' => [$this->structureController, 'delete'],
            'structure.reorder' => [$this->structureController, 'reorder'],
            'layout.index' => [$this->layoutController, 'index'],
            'layout.create' => [$this->layoutController, 'createForm'],
            'layout.store' => [$this->layoutController, 'store'],
            'layout.edit' => [$this->layoutController, 'editForm'],
            'layout.update' => [$this->layoutController, 'update'],
            'layout.reset' => [$this->layoutController, 'reset'],
            'layout.delete' => [$this->layoutController, 'delete'],
            'node.resolve' => [$this->nodeController, 'resolve'],
            default => throw new RuntimeException(sprintf('Неизвестный route ID: %s.', $routeId)),
        };
    }
}
