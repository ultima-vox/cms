<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Core\Repository\SiteRepository;
use Core\Site\SiteContext;
use RuntimeException;

final readonly class SitesApi
{
    public function __construct(
        private SiteRepository $sites,
        private ?SiteContext $adminSite = null,
    ) {
    }

    public function admin(): SiteContext
    {
        if ($this->adminSite instanceof SiteContext) {
            return $this->adminSite;
        }

        $record = $this->sites->findActiveByCode('default');
        if ($record === null) {
            $record = $this->sites->allActive()[0] ?? null;
        }
        if (!is_array($record)) {
            throw new RuntimeException('Active site context is unavailable.');
        }

        return $this->context($record);
    }

    public function adminId(): int
    {
        return $this->admin()->id;
    }

    /** @return list<SiteContext> */
    public function active(): array
    {
        return array_map(
            fn (array $record): SiteContext => $this->context($record),
            $this->sites->allActive(),
        );
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
}
