<?php

declare(strict_types=1);

namespace Core\Extension\Api;

final readonly class PermissionDefinition
{
    /** @param list<string> $defaultRoles */
    public function __construct(
        public string $code,
        public string $name,
        public array $defaultRoles,
    ) {
    }
}
