<?php

declare(strict_types=1);

namespace Core;

use Core\Extension\Api\RoutesApi;
use Core\Http\Request;
use Core\Http\Response;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use RuntimeException;

use function FastRoute\cachedDispatcher;

final class Router
{
    public function __construct(
        private readonly RoutesApi $routes,
        private readonly string $rootPath,
    ) {
    }

    public function dispatch(Request $request): Response
    {
        $definitions = $this->routes->definitions();
        $routeCacheVersion = $this->routes->signature();

        $dispatcher = cachedDispatcher(
            static function (RouteCollector $router) use ($definitions): void {
                foreach ($definitions as $definition) {
                    $router->addRoute($definition->methods, $definition->path, $definition->name);
                }
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

        $handler = $this->routes->handler($routeId);
        $response = $handler($request, $variables);

        if (!$response instanceof Response) {
            throw new RuntimeException('Контроллер должен вернуть Core\\Http\\Response.');
        }

        return $response;
    }
}
