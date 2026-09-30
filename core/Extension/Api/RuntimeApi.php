<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Core\Delivery\Cache\CacheBackendInterface;
use Core\Site\SiteContext;
use PDO;

final readonly class RuntimeApi
{
    public function __construct(
        private PDO $database,
        private string $rootPath,
        private ?SiteContext $adminSite = null,
        private ?CacheBackendInterface $cacheBackend = null,
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

    public function cacheBackend(): ?CacheBackendInterface
    {
        return $this->cacheBackend;
    }
}
