<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use LogicException;
use RuntimeException;

final class AdminApi
{
    /** @var array<string, AdminNavigationItem> */
    private array $navigation = [];

    private bool $frozen = false;

    public function navigation(
        string $code,
        string $label,
        string $path,
        ?string $permission = null,
        int $order = 100,
        string $icon = 'module',
    ): void {
        $this->assertMutable();
        $code = trim($code);
        $label = trim($label);
        $path = trim($path);
        $permission = $permission !== null ? trim($permission) : null;
        $icon = trim($icon);

        if (!preg_match('/^[a-z][a-z0-9._-]{0,79}$/', $code)) {
            throw new RuntimeException('Admin navigation code is invalid.');
        }
        if ($label === '' || preg_match_all('/./u', $label) > 80) {
            throw new RuntimeException('Admin navigation label is required and must not exceed 80 characters.');
        }
        if (!str_starts_with($path, '/admin') || preg_match('/[\r\n]/', $path)) {
            throw new RuntimeException('Admin navigation path must start with /admin.');
        }
        if ($permission !== null && !preg_match('/^[a-z][a-z0-9._-]{0,127}$/', $permission)) {
            throw new RuntimeException('Admin navigation permission code is invalid.');
        }
        if (!preg_match('/^[a-z][a-z0-9-]{0,39}$/', $icon)) {
            throw new RuntimeException('Admin navigation icon code is invalid.');
        }
        if (isset($this->navigation[$code])) {
            throw new RuntimeException(sprintf('Admin navigation item "%s" is already registered.', $code));
        }

        $this->navigation[$code] = new AdminNavigationItem(
            $code,
            $label,
            $path,
            $permission !== '' ? $permission : null,
            $order,
            $icon,
        );
    }

    /** @return list<AdminNavigationItem> */
    public function items(): array
    {
        $items = array_values($this->navigation);
        usort(
            $items,
            static fn (AdminNavigationItem $left, AdminNavigationItem $right): int =>
                [$left->order, $left->code] <=> [$right->order, $right->code],
        );

        return $items;
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Admin registry is frozen. Registration is only allowed during application boot.');
        }
    }
}
