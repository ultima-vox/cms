<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use PDO;

final readonly class RuntimeApi
{
    public function __construct(
        private PDO $database,
        private string $rootPath,
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
}
