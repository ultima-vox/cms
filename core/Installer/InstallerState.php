<?php

declare(strict_types=1);

namespace Core\Installer;

final readonly class InstallerState
{
    /** @param array<string, string>|null $environment */
    public function __construct(
        private string $rootPath,
        private ?array $environment = null,
    ) {
    }

    public function installationRequired(): bool
    {
        if (is_file($this->lockPath())) {
            return false;
        }

        // Backward compatibility for deployments created before the explicit
        // installation lock, including platforms that inject configuration
        // through process environment instead of a project .env file.
        if (is_file($this->rootPath . '/.env') || $this->hasExternalConfiguration()) {
            return false;
        }

        return true;
    }

    public function lockPath(): string
    {
        return $this->rootPath . '/storage/install.lock';
    }

    private function hasExternalConfiguration(): bool
    {
        foreach (['APP_ENV', 'APP_URL', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'] as $name) {
            $value = $this->environment === null
                ? getenv($name)
                : ($this->environment[$name] ?? null);

            if (!is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }
}
