<?php

declare(strict_types=1);

namespace Core\Extension\Api;

final readonly class ViewDefinition
{
    public function __construct(
        public string $code,
        public string $sourceType,
        public string $templatePath,
    ) {
    }
}
