<?php

declare(strict_types=1);

namespace Core\Installer;

use RuntimeException;

final readonly class SiteAdminConfig
{
    public function __construct(
        public string $siteName,
        public string $appUrl,
        public string $domain,
        public string $adminEmail,
        public string $adminName,
        public string $adminPassword,
    ) {
        if ($siteName === '' || mb_strlen($siteName) > 255) {
            throw new RuntimeException('Название сайта обязательно и не должно превышать 255 символов.');
        }

        $url = parse_url($appUrl);
        if (!is_array($url)
            || !in_array($url['scheme'] ?? null, ['http', 'https'], true)
            || !is_string($url['host'] ?? null)
            || strtolower((string) $url['host']) !== $domain) {
            throw new RuntimeException('URL сайта должен быть http/https и соответствовать указанному домену.');
        }

        if (!preg_match('/^[a-z0-9.-]{1,253}$/', $domain)
            || str_starts_with($domain, '.')
            || str_ends_with($domain, '.')
            || str_contains($domain, '..')) {
            throw new RuntimeException('Некорректное доменное имя сайта.');
        }

        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false || strlen($adminEmail) > 320) {
            throw new RuntimeException('Некорректный email администратора.');
        }
        if ($adminName === '' || mb_strlen($adminName) > 255) {
            throw new RuntimeException('Имя администратора обязательно и не должно превышать 255 символов.');
        }
        if (strlen($adminPassword) < 12) {
            throw new RuntimeException('Пароль администратора должен содержать не менее 12 символов.');
        }
        if (strlen($adminPassword) > 1024) {
            throw new RuntimeException('Пароль администратора имеет недопустимую длину.');
        }
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $siteName = trim((string) ($input['site_name'] ?? ''));
        $appUrl = rtrim(trim((string) ($input['app_url'] ?? '')), '/');
        $domain = strtolower(trim((string) ($input['domain'] ?? '')));
        $adminEmail = strtolower(trim((string) ($input['admin_email'] ?? '')));
        $adminName = trim((string) ($input['admin_name'] ?? ''));
        $adminPassword = (string) ($input['admin_password'] ?? '');
        $confirmation = (string) ($input['admin_password_confirmation'] ?? '');

        if (!hash_equals($adminPassword, $confirmation)) {
            throw new RuntimeException('Пароль администратора и подтверждение не совпадают.');
        }

        return new self(
            siteName: $siteName,
            appUrl: $appUrl,
            domain: $domain,
            adminEmail: $adminEmail,
            adminName: $adminName,
            adminPassword: $adminPassword,
        );
    }
}
