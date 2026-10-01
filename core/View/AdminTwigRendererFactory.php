<?php

declare(strict_types=1);

namespace Core\View;

use Core\Extension\Api\AdminApi;
use Core\Extension\Api\SitesApi;
use Core\Security\AuthService;

final readonly class AdminTwigRendererFactory
{
    public function __construct(
        private string $rootPath,
        private AuthService $auth,
        private AdminApi $admin,
        private SitesApi $sites,
    ) {
    }

    /** @param list<string> $additionalPaths */
    public function create(array $additionalPaths = []): TwigRenderer
    {
        $renderer = new TwigRenderer($this->rootPath, $additionalPaths);
        $renderer->addGlobal(
            'admin_shell',
            new AdminShellContext($this->auth, $this->admin, $this->sites),
        );

        return $renderer;
    }
}
