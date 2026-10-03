<?php

declare(strict_types=1);

namespace Core\Page;

use RuntimeException;

final readonly class PageTypeDefinition
{
    public string $code;
    public string $name;
    public string $description;

    /** @var array<string, mixed> */
    public array $configurationSchema;

    public function __construct(
        string $code,
        string $name,
        public PageExecutorInterface $executor,
        array $configurationSchema = [],
        public ?PageConfigurationValidatorInterface $configurationValidator = null,
        public ?PageTypeProvisionerInterface $provisioner = null,
        public bool $isDefault = false,
        public int $sorting = 0,
        string $description = '',
    ) {
        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z][a-z0-9._-]{0,127}$/', $code)) {
            throw new RuntimeException('Page type code is invalid.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Page type name cannot be empty.');
        }

        if (isset($configurationSchema['type']) && $configurationSchema['type'] !== 'object') {
            throw new RuntimeException('Page type configuration schema root must be an object.');
        }

        $this->code = $code;
        $this->name = $name;
        $this->description = trim($description);
        $this->configurationSchema = $configurationSchema;
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function validateConfiguration(array $configuration): array
    {
        return $this->configurationValidator?->validate($configuration) ?? $configuration;
    }

    /** @return array<string, mixed> */
    public function provisionConfiguration(PageTypeProvisioningContext $context): array
    {
        $configuration = $this->provisioner !== null
            ? $this->provisioner->provision($context)
            : $context->requestedConfiguration;

        return $this->validateConfiguration($configuration);
    }
}
