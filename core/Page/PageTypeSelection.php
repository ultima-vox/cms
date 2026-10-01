<?php

declare(strict_types=1);

namespace Core\Page;

use JsonException;
use RuntimeException;

final readonly class PageTypeSelection
{
    public const DEFAULT_CODE = 'core.content';

    /** @param array<string, mixed> $configuration */
    public function __construct(
        public string $code,
        public array $configuration,
    ) {
        if (!preg_match('/^[a-z][a-z0-9._-]{0,127}$/', $this->code)) {
            throw new RuntimeException('Page type code is invalid.');
        }
    }

    /** @param array<string, mixed> $node */
    public static function fromNode(array $node): self
    {
        $code = isset($node['page_type']) && is_string($node['page_type'])
            ? strtolower(trim($node['page_type']))
            : self::DEFAULT_CODE;

        if ($code === '') {
            $code = self::DEFAULT_CODE;
        }

        return new self(
            $code,
            self::decodeConfiguration($node['page_config'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    private static function decodeConfiguration(mixed $configuration): array
    {
        if ($configuration === null || $configuration === '') {
            return [];
        }

        if (is_string($configuration)) {
            try {
                $configuration = json_decode($configuration, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Page configuration contains invalid JSON.', 0, $exception);
            }
        }

        if (!is_array($configuration)) {
            throw new RuntimeException('Page configuration must be a JSON object.');
        }

        foreach ($configuration as $key => $_value) {
            if (!is_string($key)) {
                throw new RuntimeException('Page configuration keys must be strings.');
            }
        }

        return $configuration;
    }
}
