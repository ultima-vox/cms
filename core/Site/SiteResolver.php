<?php

declare(strict_types=1);

namespace Core\Site;

use Core\Http\Request;
use Core\Repository\SiteRepository;

final readonly class SiteResolver
{
    private ?string $configuredHost;

    public function __construct(
        private SiteRepository $sites,
        string $appUrl,
    ) {
        $host = parse_url($appUrl, PHP_URL_HOST);
        $this->configuredHost = is_string($host) ? $this->normalizeHost($host) : null;
    }

    public function resolve(Request $request): ?SiteContext
    {
        $host = $this->requestHost($request);
        if ($host === null) {
            return null;
        }

        $record = $this->sites->findActiveByHost($host);
        if ($record === null && $this->configuredHost !== null && $host === $this->configuredHost) {
            $record = $this->sites->findActiveByCode('default');
        }

        if ($record === null) {
            return null;
        }

        return new SiteContext(
            id: (int) $record['id'],
            code: (string) $record['code'],
            name: (string) $record['name'],
            host: $host,
            settings: is_array($record['settings'] ?? null) ? $record['settings'] : [],
        );
    }

    public function normalizeHost(string $rawHost): ?string
    {
        $host = strtolower(trim($rawHost));
        if ($host === '' || strlen($host) > 260) {
            return null;
        }
        if (preg_match('/[\x00-\x20\x7f,@\/\\\\]/', $host) === 1) {
            return null;
        }
        if (str_starts_with($host, '[')) {
            return null;
        }

        if (substr_count($host, ':') === 1
            && preg_match('/^(.+):([0-9]{1,5})$/D', $host, $matches) === 1) {
            $port = (int) $matches[2];
            if ($port < 1 || $port > 65535) {
                return null;
            }
            $host = $matches[1];
        }

        if (str_contains($host, ':')) {
            return null;
        }

        $host = rtrim($host, '.');
        if ($host === '' || strlen($host) > 253) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $host;
        }

        foreach (explode('.', $host) as $label) {
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label) !== 1) {
                return null;
            }
        }

        return $host;
    }

    private function requestHost(Request $request): ?string
    {
        $raw = $request->server['HTTP_HOST'] ?? $request->server['SERVER_NAME'] ?? null;

        return is_string($raw) ? $this->normalizeHost($raw) : null;
    }
}
