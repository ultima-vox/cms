<?php

declare(strict_types=1);

namespace Core\Page;

use RuntimeException;

final readonly class PageTypeProvisioningContext
{
    /** @var array<string, mixed> */
    public array $requestedConfiguration;

    public function __construct(
        public int $siteId,
        public int $nodeId,
        public string $nodeName,
        public string $nodeTitle,
        public string $nodePath,
        array $requestedConfiguration = [],
    ) {
        if ($siteId < 1) {
            throw new RuntimeException('Provisioning context site id must be positive.');
        }
        if ($nodeId < 1) {
            throw new RuntimeException('Provisioning context node id must be positive.');
        }
        if (trim($nodeName) === '') {
            throw new RuntimeException('Provisioning context node name cannot be empty.');
        }
        if ($nodePath === '' || $nodePath[0] !== '/') {
            throw new RuntimeException('Provisioning context node path must be absolute.');
        }

        $this->requestedConfiguration = $requestedConfiguration;
    }
}
