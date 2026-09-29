<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Closure;
use LogicException;
use RuntimeException;

final class RoutesApi
{
    /** @var array<string, RouteDefinition> */
    private array $definitions = [];

    /** @var array<string, string> */
    private array $routeKeys = [];

    private bool $frozen = false;

    public function get(string $path, string $name, callable $handler): void
    {
        $this->add(['GET'], $path, $name, $handler);
    }

    public function post(string $path, string $name, callable $handler): void
    {
        $this->add(['POST'], $path, $name, $handler);
    }

    /** @param string|list<string> $methods */
    public function add(string|array $methods, string $path, string $name, callable $handler): void
    {
        $this->assertMutable();

        $normalizedMethods = $this->normalizeMethods($methods);
        $path = trim($path);
        $name = trim($name);

        if ($path === '' || $path[0] !== '/') {
            throw new RuntimeException('Route path must start with /.');
        }

        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $name)) {
            throw new RuntimeException('Route name contains unsupported characters.');
        }

        if (isset($this->definitions[$name])) {
            throw new RuntimeException(sprintf('Route "%s" is already registered.', $name));
        }

        foreach ($normalizedMethods as $method) {
            $key = $method . ' ' . $path;
            if (isset($this->routeKeys[$key])) {
                throw new RuntimeException(sprintf(
                    'Route %s is already registered by "%s".',
                    $key,
                    $this->routeKeys[$key],
                ));
            }
        }

        $definition = new RouteDefinition(
            $normalizedMethods,
            $path,
            $name,
            Closure::fromCallable($handler),
        );
        $this->definitions[$name] = $definition;

        foreach ($normalizedMethods as $method) {
            $this->routeKeys[$method . ' ' . $path] = $name;
        }
    }

    /** @return list<RouteDefinition> */
    public function definitions(): array
    {
        return array_values($this->definitions);
    }

    public function handler(string $name): Closure
    {
        $definition = $this->definitions[$name] ?? null;

        if ($definition === null) {
            throw new RuntimeException(sprintf('Unknown route: %s.', $name));
        }

        return $definition->handler;
    }

    public function signature(): string
    {
        $serializable = [];

        foreach ($this->definitions as $definition) {
            $serializable[] = [$definition->methods, $definition->path, $definition->name];
        }

        return hash('sha256', json_encode($serializable, JSON_THROW_ON_ERROR));
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Route registry is frozen. Registration is only allowed during application boot.');
        }
    }

    /** @param string|list<string> $methods
     *  @return list<string>
     */
    private function normalizeMethods(string|array $methods): array
    {
        $methods = is_string($methods) ? [$methods] : $methods;
        $normalized = [];

        foreach ($methods as $method) {
            $method = strtoupper(trim((string) $method));
            if ($method === '' || !preg_match('/^[A-Z]+$/', $method)) {
                throw new RuntimeException('Invalid HTTP method.');
            }
            $normalized[$method] = true;
        }

        if ($normalized === []) {
            throw new RuntimeException('At least one HTTP method is required.');
        }

        return array_keys($normalized);
    }
}
