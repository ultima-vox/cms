<?php

declare(strict_types=1);

namespace Core;

use Core\Controller\AdminController;
use Core\Controller\HealthController;
use Core\Controller\NodeController;
use Core\Http\Request;
use Core\Http\Response;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

final class Router
{
    public function __construct(
        private readonly AdminController $adminController,
        private readonly HealthController $healthController,
        private readonly NodeController $nodeController,
    ) {
    }

    public function dispatch(Request $request): Response
    {
        $dispatcher = simpleDispatcher(function (RouteCollector $router): void {
            $router->addRoute('GET', '/health', [$this->healthController, 'index']);
            $router->addRoute('GET', '/admin', [$this->adminController, 'index']);
            $router->addRoute('GET', '/', [$this->nodeController, 'resolve']);
            $router->addRoute('GET', '/{path:.+}', [$this->nodeController, 'resolve']);
        });

        $routeInfo = $dispatcher->dispatch($request->method, $request->path);

        if ($routeInfo[0] === Dispatcher::NOT_FOUND) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            return Response::html('<h1>405 Method Not Allowed</h1>', 405)
                ->withHeader('Allow', implode(', ', $routeInfo[1]));
        }

        $handler = $routeInfo[1];
        $variables = $routeInfo[2] ?? [];

        if (!is_callable($handler)) {
            throw new \RuntimeException('Маршрут содержит некорректный обработчик.');
        }

        $response = $handler($request, $variables);

        if (!$response instanceof Response) {
            throw new \RuntimeException('Контроллер должен вернуть Core\\Http\\Response.');
        }

        return $response;
    }
}
