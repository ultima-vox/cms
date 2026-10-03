<?php

declare(strict_types=1);

namespace Core\Page;

interface PageTypeProvisionerInterface
{
    /** @return array<string, mixed> */
    public function provision(PageTypeProvisioningContext $context): array;
}
