<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use LogicException;
use RuntimeException;

final class ExtensionsApi
{
    /** @var array<string, array<string, mixed>> */
    private array $extensions = [];

    private bool $frozen = false;

    public function register(string $point, string $name, mixed $extension): void
    {
        $this->assertMutable();
        $point = $this->normalizeKey($point, 'extension point');
        $name = $this->normalizeKey($name, 'extension name');

        if (array_key_exists($name, $this->extensions[$point] ?? [])) {
            throw new RuntimeException(sprintf('Extension "%s:%s" is already registered.', $point, $name));
        }

        $this->extensions[$point][$name] = $extension;
    }

    public function get(string $point, string $name): mixed
    {
        return $this->extensions[$point][$name]
            ?? throw new RuntimeException(sprintf('Unknown extension "%s:%s".', $point, $name));
    }

    /** @return array<string, mixed> */
    public function all(string $point): array
    {
        return $this->extensions[$point] ?? [];
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Extension registry is frozen. Registration is only allowed during application boot.');
        }
    }

    private function normalizeKey(string $value, string $label): string
    {
        $value = trim($value);
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $value)) {
            throw new RuntimeException(sprintf('Invalid %s.', $label));
        }

        return $value;
    }
}
