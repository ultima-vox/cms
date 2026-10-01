<?php

declare(strict_types=1);

namespace Core\View;

use Core\Extension\Api\AdminApi;
use Core\Extension\Api\AdminNavigationItem;
use Core\Extension\Api\SitesApi;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\Site\SiteContext;

final class AdminShellContext
{
    /** @var list<AdminNavigationItem>|null */
    private ?array $navigation = null;

    /** @var list<SiteContext>|null */
    private ?array $sitesCache = null;

    /** @var array<string, mixed>|null */
    private ?array $userCache = null;

    private bool $userResolved = false;

    public function __construct(
        private readonly AuthService $auth,
        private readonly AdminApi $admin,
        private readonly SitesApi $sites,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if (!$this->userResolved) {
            $this->userCache = $this->auth->user();
            $this->userResolved = true;
        }

        return $this->userCache;
    }

    /** @return list<AdminNavigationItem> */
    public function navigation(): array
    {
        if ($this->navigation !== null) {
            return $this->navigation;
        }

        $this->navigation = array_values(array_filter(
            $this->admin->items(),
            fn (AdminNavigationItem $item): bool => $item->permission === null || $this->auth->can($item->permission),
        ));

        return $this->navigation;
    }

    public function site(): SiteContext
    {
        return $this->sites->admin();
    }

    /** @return list<SiteContext> */
    public function sites(): array
    {
        return $this->sitesCache ??= $this->sites->active();
    }

    public function csrfToken(): string
    {
        return Csrf::token();
    }
}
