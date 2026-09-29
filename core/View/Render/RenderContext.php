<?php

declare(strict_types=1);

namespace Core\View\Render;

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
}
