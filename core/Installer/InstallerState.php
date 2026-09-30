<?php

declare(strict_types=1);

namespace Core\Installer;

final readonly class InstallerState
{
    public function __construct(private string $rootPath)
    {
    }

    public function installationRequired(): bool
    {
        if (is_file($this->lockPath())) {
            return false;
        }

        // Backward compatibility for existing deployments created before the
        // explicit installation lock was introduced.
        return !is_file($this->rootPath . '/.env');
    }

    public function lockPath(): string
    {
        return $this->rootPath . '/storage/install.lock';
    }
}
