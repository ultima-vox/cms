<?php

declare(strict_types=1);

namespace Core;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

final class Router
{
    private function __construct()
    {
    }

    public static function dispatch(string $httpMethod, string $uri): void
    {
        $dispatcher = simpleDispatcher(
            static function (RouteCollector $router): void {
                $router->addRoute('GET', '/admin', 'AdminController@index');
                $router->addRoute('GET', '/', 'NodeController@resolve');
                $router->addRoute('GET', '/{path:.+}', 'NodeController@resolve');
            }
        );

        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        $routeInfo = $dispatcher->dispatch($httpMethod, $path);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                http_response_code(404);
                echo '404 Not Found';
                return;

            case Dispatcher::METHOD_NOT_ALLOWED:
                http_response_code(405);
                header('Allow: ' . implode(', ', $routeInfo[1]));
                echo '405 Method Not Allowed';
                return;

            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $variables = $routeInfo[2] ?? [];

                if (!is_string($handler) || !str_contains($handler, '@')) {
                    throw new \RuntimeException('Некорректный обработчик маршрута.');
                }

                [$controller, $method] = explode('@', $handler, 2);

                self::invokeController($controller, $method, $variables);
                return;
        }
    }

    /**
     * @param array<string, string> $variables
     */
    private static function invokeController(
        string $controller,
        string $method,
        array $variables,
    ): void {
        $class = str_contains($controller, '\\')
            ? $controller
            : 'App\\Controller\\' . $controller;

        if (!class_exists($class)) {
            throw new \RuntimeException(sprintf(
                'Контроллер %s не найден.',
                $class,
            ));
        }

        $instance = new $class();

        if (!is_callable([$instance, $method])) {
            throw new \RuntimeException(sprintf(
                'Метод %s::%s() не найден или недоступен.',
                $class,
                $method,
            ));
        }

        $instance->{$method}($variables);
    }
}
