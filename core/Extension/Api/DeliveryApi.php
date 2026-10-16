<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Core\Delivery\Page\PageCachePolicy;
use Core\Delivery\StaticPage\StaticPagePublisher;
use Core\Http\Request;
use Core\Site\SiteContext;
use Core\View\Render\RenderResult;
use LogicException;
use RuntimeException;

final class DeliveryApi
{
    /** @var array<string, callable(Request,SiteContext):bool> */
    private array $pageCacheRules = [];

    private bool $frozen = false;

    public function __construct(
        private readonly CacheApi $cache,
        private readonly StaticPagePublisher $staticPages,
    ) {
    }

    /** @param list<string> $dependencies @return array{cache:int,static:int} */
    public function invalidate(array $dependencies): array
    {
        return [
            'cache' => $this->cache->invalidate($dependencies),
            'static' => $this->staticPages->invalidate($dependencies),
        ];
    }

    /** @param list<string> $dependencies */
    public function publishStatic(
        int $siteId,
        string $path,
        string $html,
        array $dependencies,
        string $permalinkStyle = 'clean',
    ): string {
        return $this->staticPages->publish($siteId, $path, $html, $dependencies, $permalinkStyle);
    }

    /** @return array{token:string,started_at:int} */
    public function beginStaticGeneration(): array
    {
        $startedAt = time();
        return ['token' => $this->staticPages->generation(), 'started_at' => $startedAt];
    }

    /** @param array{token:string,started_at:int} $generation */
    public function publishRenderedStatic(
        int $siteId,
        string $path,
        RenderResult $result,
        array $generation,
        string $permalinkStyle = 'clean',
    ): string {
        if (
            !is_string($generation['token'] ?? null)
            || preg_match('/^[a-f0-9]{32}$/D', $generation['token']) !== 1
            || !is_int($generation['started_at'] ?? null)
            || $generation['started_at'] < 1 || $generation['started_at'] > time()
        ) {
            throw new RuntimeException('Invalid static generation token.');
        }
        if (!$result->context->isCacheable()) {
            $this->deleteStatic($siteId, $path, $permalinkStyle, $generation['token']);
            throw new RuntimeException('Static generation was vetoed by the render cache policy.');
        }

        return $this->staticPages->publish(
            $siteId,
            $path,
            $result->html,
            $result->context->dependencies(),
            $permalinkStyle,
            $generation['token'],
            $generation['started_at'] + $result->context->maxAge(300),
        );
    }

    public function deleteStatic(
        int $siteId,
        string $path,
        string $permalinkStyle = 'clean',
        ?string $expectedGeneration = null,
    ): void {
        $this->staticPages->delete($siteId, $path, $permalinkStyle, $expectedGeneration);
    }

    public function readStatic(int $siteId, string $path, string $permalinkStyle = 'clean'): ?string
    {
        return $this->staticPages->read($siteId, $path, $permalinkStyle);
    }

    public function pruneExpiredStatic(): int
    {
        return $this->staticPages->pruneExpired();
    }

    public function staticPath(int $siteId, string $path, string $permalinkStyle = 'clean'): string
    {
        return $this->staticPages->path($siteId, $path, $permalinkStyle);
    }

    /** @param callable(Request,SiteContext):bool $rule */
    public function pageCacheRule(string $code, callable $rule): void
    {
        $this->assertMutable();
        $code = trim($code);
        if (!preg_match('/^[a-z][a-z0-9._-]{0,119}$/D', $code)) {
            throw new LogicException('Invalid page-cache rule code.');
        }
        if (isset($this->pageCacheRules[$code])) {
            throw new LogicException(sprintf('Page-cache rule "%s" is already registered.', $code));
        }

        $this->pageCacheRules[$code] = $rule;
    }

    public function pageCacheAllowed(Request $request, SiteContext $site, bool $authenticated): bool
    {
        if (!PageCachePolicy::requestEligible($request, $authenticated)) {
            return false;
        }

        foreach ($this->pageCacheRules as $rule) {
            if ($rule($request, $site) !== true) {
                return false;
            }
        }

        return true;
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Delivery registry is frozen.');
        }
    }
}
