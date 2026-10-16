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

        if ($definition->provisioner === null) {
            throw new RuntimeException(sprintf(
                'Page type "%s" cannot be provisioned from Structure.',
                $definition->code,
            ));
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $nodeId = $this->structure->create(
                $nodeData,
                $definition->code,
            );
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

    /** @param array<string, mixed> $requestedConfiguration */
    public function changeType(
        int $nodeId,
        string $pageType,
        string $expectedPageType,
        array $requestedConfiguration = [],
    ): void {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        } else {
            $this->db->exec('SAVEPOINT structure_page_type_change');
        }

        try {
            $node = $this->structure->find($nodeId, true);
            if ($node === null) {
                throw new RuntimeException('Узел не найден для текущего сайта.');
            }
            if ((string) $node['page_type'] !== $expectedPageType) {
                throw new RuntimeException('Тип страницы уже изменён. Обновите форму и повторите действие.');
            }

            $pageType = strtolower(trim($pageType));
            if ($pageType !== (string) $node['page_type']) {
                $definition = $this->pages->definition($pageType);
                if ($definition->provisioner === null) {
                    throw new RuntimeException('Этот тип страницы не поддерживает автоматическую настройку ресурса.');
                }

                $siteId = (int) $node['site_id'];
                $configuration = $this->pages->provisionConfiguration(
                    $definition->code,
                    new PageTypeProvisioningContext(
                        siteId: $siteId,
                        nodeId: $nodeId,
                        nodeName: (string) $node['name'],
                        nodeTitle: (string) ($node['title'] ?? ''),
                        nodePath: (string) $node['path'],
                        requestedConfiguration: $requestedConfiguration,
                    ),
                );
                $this->pageSelections->assign($nodeId, $siteId, $definition->code, $configuration);
            }

            if ($ownsTransaction) {
                $this->db->commit();
            } else {
                $this->db->exec('RELEASE SAVEPOINT structure_page_type_change');
            }
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                if ($ownsTransaction) {
                    $this->db->rollBack();
                } else {
                    $this->db->exec('ROLLBACK TO SAVEPOINT structure_page_type_change');
                    $this->db->exec('RELEASE SAVEPOINT structure_page_type_change');
                }
            }
            throw $exception;
        }
    }
}
