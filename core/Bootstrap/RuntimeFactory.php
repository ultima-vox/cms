<?php

declare(strict_types=1);

namespace Core\Bootstrap;

use Core\Extension\Api\RuntimeApi;
use Core\Repository\AuditLogRepository;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\UserRepository;
use Core\Security\AuthService;
use Core\View\TwigRenderer;
use PDO;

final class RuntimeFactory
{
    public function create(PDO $db, string $rootPath): RuntimeApi
    {
        $twig = new TwigRenderer($rootPath);
        $auth = new AuthService(
            new UserRepository($db),
            new LoginAttemptRepository($db),
        );
        $audit = new AuditLogRepository($db);

        return new RuntimeApi(
            $db,
            $rootPath,
            $auth,
            $audit,
            $twig,
        );
    }
}
