<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Core\Delivery\Page\PageCachePolicy;
use Core\Delivery\StaticPage\StaticPagePublisher;
use Core\Http\Request;
use Core\Site\SiteContext;
use LogicException;

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
    ): string {
        return $this->staticPages->publish($siteId, $path, $html, $dependencies);
    }

    public function deleteStatic(int $siteId, string $path): void
    {
        $this->staticPages->delete($siteId, $path);
    }

    public function staticPath(int $siteId, string $path): string
    {
        return $this->staticPages->path($siteId, $path);
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
