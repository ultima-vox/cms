<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Closure;

final readonly class RouteDefinition
{
    /** @param list<string> $methods */
    public function __construct(
        public array $methods,
        public string $path,
        public string $name,
        public Closure $handler,
    ) {
    }
}
