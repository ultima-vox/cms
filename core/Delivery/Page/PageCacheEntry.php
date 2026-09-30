<?php

declare(strict_types=1);

namespace Core\Delivery\Page;

use Core\Delivery\ResourceHint;
use RuntimeException;

final readonly class PageCacheEntry
{
    /**
     * @param list<string> $dependencies
     * @param list<ResourceHint> $resourceHints
     */
    public function __construct(
        public string $html,
        public array $dependencies,
        public array $resourceHints,
        public int $createdAt,
        public int $maxAge,
    ) {
        if ($this->maxAge < 1) {
            throw new RuntimeException('Page cache max-age must be positive.');
        }
    }

    public function encode(): string
    {
        return json_encode([
            'version' => 1,
            'html' => base64_encode($this->html),
            'dependencies' => $this->dependencies,
            'resource_hints' => array_map(
                static fn (ResourceHint $hint): array => [
                    'rel' => $hint->rel,
                    'href' => $hint->href,
                    'attributes' => $hint->attributes,
                ],
                $this->resourceHints,
            ),
            'created_at' => $this->createdAt,
            'max_age' => $this->maxAge,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public static function decode(string $payload): ?self
    {
        try {
            $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_string($data['html'] ?? null)
            || !is_array($data['dependencies'] ?? null)
            || !is_array($data['resource_hints'] ?? null)
            || !is_int($data['created_at'] ?? null)
            || !is_int($data['max_age'] ?? null)
            || $data['max_age'] < 1) {
            return null;
        }

        $html = base64_decode($data['html'], true);
        if (!is_string($html)) {
            return null;
        }

        $dependencies = [];
        foreach ($data['dependencies'] as $dependency) {
            if (!is_string($dependency)) {
                return null;
            }
            $dependencies[] = $dependency;
        }

        $hints = [];
        try {
            foreach ($data['resource_hints'] as $rawHint) {
                if (!is_array($rawHint)
                    || !is_string($rawHint['rel'] ?? null)
                    || !is_string($rawHint['href'] ?? null)
                    || !is_array($rawHint['attributes'] ?? null)) {
                    return null;
                }

                $attributes = [];
                foreach ($rawHint['attributes'] as $name => $value) {
                    if (!is_string($name) || !is_string($value)) {
                        return null;
                    }
                    $attributes[$name] = $value;
                }

                $hints[] = new ResourceHint(
                    $rawHint['rel'],
                    $rawHint['href'],
                    $attributes,
                );
            }
        } catch (RuntimeException) {
            return null;
        }

        return new self(
            $html,
            array_values(array_unique($dependencies)),
            $hints,
            $data['created_at'],
            $data['max_age'],
        );
    }
}
