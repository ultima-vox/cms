<?php

declare(strict_types=1);

namespace Core\View\Render;

use Core\Delivery\ResourceHint;

final class RenderContext
{
    /** @var array<string, true> */
    private array $dependencies = [];

    /** @var array<string, true> */
    private array $assets = [];

    /** @var array<string, true> */
    private array $imagePresets = [];

    /** @var array<string, mixed> */
    private array $schema = [];

    /** @var array<string, ResourceHint> */
    private array $resourceHints = [];

    public function dependency(string $tag): void
    {
        $tag = trim($tag);
        if ($tag !== '') {
            $this->dependencies[$tag] = true;
        }
    }

    public function asset(string $asset): void
    {
        $asset = trim($asset);
        if ($asset !== '') {
            $this->assets[$asset] = true;
        }
    }

    public function imagePreset(string $preset): void
    {
        $preset = trim($preset);
        if ($preset !== '') {
            $this->imagePresets[$preset] = true;
        }
    }

    public function schema(string $key, mixed $value): void
    {
        $this->schema[$key] = $value;
    }

    public function resourceHint(ResourceHint $hint): void
    {
        $this->resourceHints[$hint->key()] = $hint;
    }

    /** @param array<string, string> $attributes */
    public function preload(string $href, array $attributes = []): void
    {
        $this->resourceHint(new ResourceHint('preload', $href, $attributes));
    }

    public function preconnect(string $href, bool $crossorigin = false): void
    {
        $this->resourceHint(new ResourceHint(
            'preconnect',
            $href,
            $crossorigin ? ['crossorigin' => 'anonymous'] : [],
        ));
    }

    public function modulePreload(string $href): void
    {
        $this->resourceHint(new ResourceHint('modulepreload', $href));
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return array_keys($this->dependencies);
    }

    /** @return list<string> */
    public function assets(): array
    {
        return array_keys($this->assets);
    }

    /** @return list<string> */
    public function imagePresets(): array
    {
        return array_keys($this->imagePresets);
    }

    /** @return array<string, mixed> */
    public function schemas(): array
    {
        return $this->schema;
    }

    /** @return list<ResourceHint> */
    public function resourceHints(): array
    {
        return array_values($this->resourceHints);
    }
}
