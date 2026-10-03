<?php

declare(strict_types=1);

namespace Core\Page;

interface PageConfigurationValidatorInterface
{
    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function validate(array $configuration): array;
}
