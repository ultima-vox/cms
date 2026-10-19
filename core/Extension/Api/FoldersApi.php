<?php

declare(strict_types=1);
namespace Core\Extension\Api;

use LogicException;
use RuntimeException;

/** Modules declare their folder scope; Core never queries module-owned content tables. */
final class FoldersApi
{
    private array $scopes = [];
    private bool $frozen = false;
    public function define(string $scope, string $label, string $path, string $permission): void
    {
        if ($this->frozen) throw new LogicException('Folder registry is frozen.');
        if (!preg_match('/^[a-z][a-z0-9._-]{0,79}$/D', $scope) || isset($this->scopes[$scope])
            || !preg_match('~^/admin/[a-z][a-z0-9/_-]*$~D', $path)
            || !preg_match('/^[a-z][a-z0-9._-]{0,127}$/D', $permission) || trim($label) === '') throw new RuntimeException('Invalid folder scope.');
        $this->scopes[$scope] = compact('scope', 'label', 'path', 'permission');
    }
    public function definition(string $scope): ?array { return $this->scopes[$scope] ?? null; }
    public function freeze(): void { $this->frozen = true; }
}
