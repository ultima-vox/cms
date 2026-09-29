<?php

declare(strict_types=1);

namespace Core\Admin;

use Core\Extension\Api\AdminApi;
use Core\Extension\Api\AdminNavigationItem;
use Core\Security\AuthService;

final readonly class AdminNavigationContext
{
    public function __construct(
        private AdminApi $admin,
        private AuthService $auth,
    ) {
    }

    /** @return list<array{code:string,label:string,path:string,active:bool}> */
    public function forPath(string $currentPath): array
    {
        if ($this->auth->user() === null) {
            return [];
        }

        $navigation = [];

        foreach ($this->admin->items() as $item) {
            if (!$this->isAllowed($item)) {
                continue;
            }

            $navigation[] = [
                'code' => $item->code,
                'label' => $item->label,
                'path' => $item->path,
                'active' => $this->isActive($item->path, $currentPath),
            ];
        }

        return $navigation;
    }

    private function isAllowed(AdminNavigationItem $item): bool
    {
        return $item->permission === null || $this->auth->can($item->permission);
    }

    private function isActive(string $itemPath, string $currentPath): bool
    {
        if ($itemPath === '/admin') {
            return $currentPath === '/admin' || $currentPath === '/admin/';
        }

        return $currentPath === $itemPath || str_starts_with($currentPath, $itemPath . '/');
    }
}
