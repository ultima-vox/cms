<?php

declare(strict_types=1);

namespace Core\Extension;

use Core\Delivery\Cache\FilesystemCacheStore;
use Core\Delivery\Cache\FilesystemTagIndex;
use Core\Delivery\Cache\TaggedCache;
use Core\Extension\Api\AdminApi;
use Core\Extension\Api\CacheApi;
use Core\Extension\Api\ContentApi;
use Core\Extension\Api\EventsApi;
use Core\Extension\Api\ExtensionsApi;
use Core\Extension\Api\PermissionsApi;
use Core\Extension\Api\RoutesApi;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Api\SitesApi;
use Core\Extension\Api\TemplatesApi;
use Core\Repository\SiteRepository;

final class Core
{
    private RoutesApi $routes;
    private TemplatesApi $templates;
    private ContentApi $content;
    private AdminApi $admin;
    private PermissionsApi $permissions;
    private EventsApi $events;
    private ExtensionsApi $extensions;
    private SitesApi $sites;
    private CacheApi $cache;
    private bool $frozen = false;

    public function __construct(private readonly RuntimeApi $runtime)
    {
        $this->routes = new RoutesApi();
        $this->templates = new TemplatesApi();
        $this->content = new ContentApi();
        $this->admin = new AdminApi();
        $this->permissions = new PermissionsApi();
        $this->events = new EventsApi();
        $this->extensions = new ExtensionsApi();
        $this->sites = new SitesApi(
            new SiteRepository($runtime->database()),
            $runtime->adminSite(),
        );

        $backend = $runtime->cacheBackend() ?? new TaggedCache(
            new FilesystemCacheStore($runtime->rootPath() . '/storage/cache/data'),
            new FilesystemTagIndex($runtime->rootPath() . '/storage/cache/index'),
        );
        $this->cache = new CacheApi($backend);
    }

    public function runtime(): RuntimeApi
    {
        return $this->runtime;
    }

    public function routes(): RoutesApi
    {
        return $this->routes;
    }

    public function templates(): TemplatesApi
    {
        return $this->templates;
    }

    public function content(): ContentApi
    {
        return $this->content;
    }

    public function admin(): AdminApi
    {
        return $this->admin;
    }

    public function permissions(): PermissionsApi
    {
        return $this->permissions;
    }

    public function events(): EventsApi
    {
        return $this->events;
    }

    public function sites(): SitesApi
    {
        return $this->sites;
    }

    public function cache(): CacheApi
    {
        return $this->cache;
    }

    /**
     * Escape hatch for extension points that do not yet have a typed API.
     * Stable features should graduate to a dedicated typed facade.
     */
    public function extensions(): ExtensionsApi
    {
        return $this->extensions;
    }

    public function freeze(): void
    {
        if ($this->frozen) {
            return;
        }

        $this->routes->freeze();
        $this->templates->freeze();
        $this->content->freeze();
        $this->admin->freeze();
        $this->permissions->freeze();
        $this->events->freeze();
        $this->extensions->freeze();
        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }
}
