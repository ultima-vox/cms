<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Core\Site\SiteContext;
use PDO;

final readonly class RuntimeApi
{
    public function __construct(
        private PDO $database,
        private string $rootPath,
        private ?SiteContext $adminSite = null,
    ) {
    }

    public function database(): PDO
    {
        return $this->database;
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function adminSite(): ?SiteContext
    {
        return $this->adminSite;
    }
}
