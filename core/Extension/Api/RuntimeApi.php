<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Core\Repository\AuditLogRepository;
use Core\Security\AuthService;
use Core\View\TwigRenderer;
use PDO;

final readonly class RuntimeApi
{
    public function __construct(
        private PDO $database,
        private string $rootPath,
        private AuthService $auth,
        private AuditLogRepository $audit,
        private TwigRenderer $adminRenderer,
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

    public function auth(): AuthService
    {
        return $this->auth;
    }

    public function audit(): AuditLogRepository
    {
        return $this->audit;
    }

    public function adminRenderer(): TwigRenderer
    {
        return $this->adminRenderer;
    }
}
