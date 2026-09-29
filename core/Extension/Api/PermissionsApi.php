<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use LogicException;
use RuntimeException;

final class PermissionsApi
{
    /** @var array<string, PermissionDefinition> */
    private array $definitions = [];

    private bool $frozen = false;

    /** @param list<string> $defaultRoles */
    public function define(string $code, string $name, array $defaultRoles = []): void
    {
        $this->assertMutable();
        $code = trim($code);
        $name = trim($name);

        if (!preg_match('/^[a-z][a-z0-9._-]{0,127}$/', $code)) {
            throw new RuntimeException('Permission code is invalid.');
        }
        if ($name === '' || preg_match_all('/./u', $name) > 160) {
            throw new RuntimeException('Permission name is required and must not exceed 160 characters.');
        }
        if (isset($this->definitions[$code])) {
            throw new RuntimeException(sprintf('Permission "%s" is already registered.', $code));
        }

        $roles = [];
        foreach ($defaultRoles as $role) {
            $role = trim($role);
            if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $role)) {
                throw new RuntimeException(sprintf('Default role code "%s" is invalid.', $role));
            }
            $roles[$role] = true;
        }

        $this->definitions[$code] = new PermissionDefinition(
            $code,
            $name,
            array_keys($roles),
        );
    }

    /** @return list<PermissionDefinition> */
    public function definitions(): array
    {
        $definitions = array_values($this->definitions);
        usort(
            $definitions,
            static fn (PermissionDefinition $left, PermissionDefinition $right): int => $left->code <=> $right->code,
        );

        return $definitions;
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Permission registry is frozen. Registration is only allowed during application boot.');
        }
    }
}
