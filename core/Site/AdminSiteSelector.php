<?php

declare(strict_types=1);

namespace Core\Site;

use Core\Repository\SiteRepository;
use RuntimeException;

final class AdminSiteSelector
{
    private const SESSION_KEY = 'uvcms_admin_site_id';

    public function __construct(private readonly SiteRepository $sites)
    {
    }

    public function current(): SiteContext
    {
        $this->requireSession();

        $selected = $_SESSION[self::SESSION_KEY] ?? null;
        $selectedId = is_int($selected)
            ? $selected
            : (is_string($selected) && ctype_digit($selected) ? (int) $selected : 0);

        $record = $selectedId > 0 ? $this->sites->findActiveById($selectedId) : null;
        if ($record === null) {
            $record = $this->sites->findActiveByCode('default');
        }
        if ($record === null) {
            $active = $this->sites->allActive();
            $record = $active[0] ?? null;
        }
        if (!is_array($record)) {
            throw new RuntimeException('Нет активного сайта для административного контекста.');
        }

        $context = $this->context($record);
        $_SESSION[self::SESSION_KEY] = $context->id;

        return $context;
    }

    public function select(int $siteId): SiteContext
    {
        $this->requireSession();
        $record = $this->sites->findActiveById($siteId);
        if ($record === null) {
            throw new RuntimeException('Активный сайт не найден.');
        }

        $context = $this->context($record);
        $_SESSION[self::SESSION_KEY] = $context->id;

        return $context;
    }

    /** @param array<string, mixed> $record */
    private function context(array $record): SiteContext
    {
        return new SiteContext(
            id: (int) $record['id'],
            code: (string) $record['code'],
            name: (string) $record['name'],
            host: is_string($record['host'] ?? null) ? $record['host'] : '',
            settings: is_array($record['settings'] ?? null) ? $record['settings'] : [],
        );
    }

    private function requireSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Сессия должна быть запущена до выбора административного сайта.');
        }
    }
}
