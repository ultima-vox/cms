<?php

declare(strict_types=1);

namespace Core\Extension;

use Core\Extension\Api\ExtensionsApi;
use Core\Extension\Api\RoutesApi;
use Core\Extension\Api\TemplatesApi;

final class Core
{
    private RoutesApi $routes;
    private TemplatesApi $templates;
    private ExtensionsApi $extensions;
    private bool $frozen = false;

    public function __construct()
    {
        $this->routes = new RoutesApi();
        $this->templates = new TemplatesApi();
        $this->extensions = new ExtensionsApi();
    }

    public function routes(): RoutesApi
    {
        return $this->routes;
    }

    public function templates(): TemplatesApi
    {
        return $this->templates;
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
        $this->extensions->freeze();
        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }
}
