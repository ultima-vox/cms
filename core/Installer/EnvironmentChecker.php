<?php

declare(strict_types=1);

namespace Core\Installer;

final readonly class EnvironmentChecker
{
    public function __construct(private string $rootPath)
    {
    }

    /**
     * @return list<array{code:string,label:string,ok:bool,required:bool,details:string}>
     */
    public function checks(): array
    {
        $checks = [
            $this->check(
                'php',
                'PHP 8.4+',
                version_compare(PHP_VERSION, '8.4.0', '>='),
                true,
                PHP_VERSION,
            ),
        ];

        foreach (['dom', 'json', 'mbstring', 'pdo', 'pdo_pgsql'] as $extension) {
            $checks[] = $this->check(
                'ext-' . $extension,
                'PHP extension: ' . $extension,
                extension_loaded($extension),
                true,
                extension_loaded($extension) ? 'доступно' : 'не загружено',
            );
        }

        $checks[] = $this->check(
            'ext-zip',
            'PHP extension: zip',
            extension_loaded('zip'),
            false,
            extension_loaded('zip')
                ? 'доступно для установки ZIP-модулей'
                : 'опционально; потребуется для ZIP-модулей',
        );

        $checks[] = $this->check(
            'ext-sodium',
            'PHP extension: sodium',
            extension_loaded('sodium'),
            false,
            extension_loaded('sodium')
                ? 'доступно для проверки Ed25519-подписей'
                : 'опционально; потребуется для подписанных пакетов',
        );

        $checks[] = $this->check(
            'vendor',
            'Production dependencies',
            is_file($this->rootPath . '/vendor/autoload.php'),
            true,
            'vendor/autoload.php',
        );

        $checks[] = $this->check(
            'migrations',
            'Core migrations',
            is_dir($this->rootPath . '/database/migrations')
                && is_readable($this->rootPath . '/database/migrations'),
            true,
            'database/migrations/',
        );

        $storage = $this->rootPath . '/storage';
        $checks[] = $this->check(
            'storage',
            'Writable storage',
            is_dir($storage) && is_writable($storage),
            true,
            'storage/',
        );

        $checks[] = $this->check(
            'env-target',
            'Writable .env target',
            $this->envTargetWritable(),
            true,
            is_file($this->rootPath . '/.env') ? '.env' : $this->rootPath,
        );

        $checks[] = $this->check(
            'random',
            'Cryptographically secure random source',
            $this->randomWorks(),
            true,
            'random_bytes()',
        );

        $uploadLimit = ini_get('upload_max_filesize');
        $postLimit = ini_get('post_max_size');
        $checks[] = $this->check(
            'uploads',
            'PHP upload limits',
            true,
            false,
            sprintf('upload_max_filesize=%s; post_max_size=%s', (string) $uploadLimit, (string) $postLimit),
        );

        return $checks;
    }

    /** @param list<array{code:string,label:string,ok:bool,required:bool,details:string}> $checks */
    public function requiredPassed(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['required'] && !$check['ok']) {
                return false;
            }
        }

        return true;
    }

    /** @return array{code:string,label:string,ok:bool,required:bool,details:string} */
    private function check(
        string $code,
        string $label,
        bool $ok,
        bool $required,
        string $details,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'ok' => $ok,
            'required' => $required,
            'details' => $details,
        ];
    }

    private function envTargetWritable(): bool
    {
        $env = $this->rootPath . '/.env';
        if (is_file($env)) {
            return is_writable($env);
        }

        return is_writable($this->rootPath);
    }

    private function randomWorks(): bool
    {
        try {
            return strlen(random_bytes(16)) === 16;
        } catch (\Throwable) {
            return false;
        }
    }
}
