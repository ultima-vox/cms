<?php

declare(strict_types=1);

namespace Core\Structure;

use Core\Extension\Api\PagesApi;
use Core\Page\PageTypeProvisioningContext;
use Core\Repository\PageSelectionRepository;
use Core\Repository\StructureRepository;
use PDO;
use RuntimeException;
use Throwable;

final readonly class StructurePageProvisioningService
{
    public function __construct(
        private PDO $db,
        private StructureRepository $structure,
        private PageSelectionRepository $pageSelections,
        private PagesApi $pages,
    ) {
    }

    /**
     * @param array<string, mixed> $nodeData
     * @param array<string, mixed> $requestedConfiguration
     */
    public function create(
        array $nodeData,
        ?string $pageType = null,
        array $requestedConfiguration = [],
    ): int {
        $definition = $pageType !== null && trim($pageType) !== ''
            ? $this->pages->definition($pageType)
            : $this->pages->default();

        if ($definition === null) {
            throw new RuntimeException('No default page type is registered. Select a page type explicitly.');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $nodeId = $this->structure->create($nodeData);
            $node = $this->structure->find($nodeId);
            if ($node === null) {
                throw new RuntimeException('Created Structure node could not be reloaded.');
            }

            $siteId = isset($node['site_id']) && is_numeric($node['site_id'])
                ? (int) $node['site_id']
                : 0;
            $configuration = $this->pages->provisionConfiguration(
                $definition->code,
                new PageTypeProvisioningContext(
                    siteId: $siteId,
                    nodeId: $nodeId,
                    nodeName: (string) ($node['name'] ?? ''),
                    nodeTitle: (string) ($node['title'] ?? ''),
                    nodePath: (string) ($node['path'] ?? ''),
                    requestedConfiguration: $requestedConfiguration,
                ),
            );

            $this->pageSelections->assign(
                $nodeId,
                $siteId,
                $definition->code,
                $configuration,
            );

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $nodeId;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }
}
