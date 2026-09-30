<?php

declare(strict_types=1);

namespace Core\Repository;

use PDO;
use RuntimeException;
use Throwable;

final class NodeModuleBindingRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findTargetKey(int $nodeId, string $moduleCode, string $bindingCode): ?string
    {
        if ($nodeId < 1) {
            return null;
        }

        $moduleCode = $this->moduleCode($moduleCode);
        $bindingCode = $this->bindingCode($bindingCode);

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT target_key
            FROM node_module_bindings
            WHERE node_id = :node_id
              AND module_code = :module_code
              AND binding_code = :binding_code
            LIMIT 1
            SQL
        );
        $statement->execute([
            'node_id' => $nodeId,
            'module_code' => $moduleCode,
            'binding_code' => $bindingCode,
        ]);
        $value = $statement->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<int> */
    public function nodeIdsForTarget(
        int $siteId,
        string $moduleCode,
        string $bindingCode,
        string $targetKey,
    ): array {
        if ($siteId < 1) {
            throw new RuntimeException('Site id must be positive.');
        }

        $moduleCode = $this->moduleCode($moduleCode);
        $bindingCode = $this->bindingCode($bindingCode);
        $targetKey = $this->targetKey($targetKey);

        $statement = $this->db->prepare(
            <<<'SQL'
            SELECT b.node_id
            FROM node_module_bindings b
            JOIN nodes n ON n.id = b.node_id
            WHERE n.site_id = :site_id
              AND b.module_code = :module_code
              AND b.binding_code = :binding_code
              AND b.target_key = :target_key
            ORDER BY b.node_id
            SQL
        );
        $statement->execute([
            'site_id' => $siteId,
            'module_code' => $moduleCode,
            'binding_code' => $bindingCode,
            'target_key' => $targetKey,
        ]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<int> $nodeIds */
    public function replaceTargetNodes(
        int $siteId,
        string $moduleCode,
        string $bindingCode,
        string $targetKey,
        array $nodeIds,
    ): void {
        if ($siteId < 1) {
            throw new RuntimeException('Site id must be positive.');
        }

        $moduleCode = $this->moduleCode($moduleCode);
        $bindingCode = $this->bindingCode($bindingCode);
        $targetKey = $this->targetKey($targetKey);
        $nodeIds = $this->normalizeNodeIds($nodeIds);
        $this->assertNodesBelongToSite($siteId, $nodeIds);

        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare(
                <<<'SQL'
                DELETE FROM node_module_bindings b
                USING nodes n
                WHERE b.node_id = n.id
                  AND n.site_id = :site_id
                  AND b.module_code = :module_code
                  AND b.binding_code = :binding_code
                  AND b.target_key = :target_key
                SQL
            );
            $delete->execute([
                'site_id' => $siteId,
                'module_code' => $moduleCode,
                'binding_code' => $bindingCode,
                'target_key' => $targetKey,
            ]);

            if ($nodeIds !== []) {
                $upsert = $this->db->prepare(
                    <<<'SQL'
                    INSERT INTO node_module_bindings (
                        node_id, module_code, binding_code, target_key, updated_at
                    ) VALUES (
                        :node_id, :module_code, :binding_code, :target_key, CURRENT_TIMESTAMP
                    )
                    ON CONFLICT (node_id, module_code, binding_code)
                    DO UPDATE SET
                        target_key = EXCLUDED.target_key,
                        updated_at = CURRENT_TIMESTAMP
                    SQL
                );

                foreach ($nodeIds as $nodeId) {
                    $upsert->execute([
                        'node_id' => $nodeId,
                        'module_code' => $moduleCode,
                        'binding_code' => $bindingCode,
                        'target_key' => $targetKey,
                    ]);
                }
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function moduleCode(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-z][a-z0-9_-]{0,119}$/', $value)) {
            throw new RuntimeException('Invalid module binding code.');
        }
        return $value;
    }

    private function bindingCode(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-z][a-z0-9_.-]{0,119}$/', $value)) {
            throw new RuntimeException('Invalid node binding code.');
        }
        return $value;
    }

    private function targetKey(string $value): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 255) {
            throw new RuntimeException('Invalid node binding target key.');
        }
        return $value;
    }

    /** @param list<int> $nodeIds @return list<int> */
    private function normalizeNodeIds(array $nodeIds): array
    {
        $normalized = [];
        foreach ($nodeIds as $nodeId) {
            if ($nodeId < 1) {
                throw new RuntimeException('Node id must be positive.');
            }
            $normalized[$nodeId] = $nodeId;
        }
        ksort($normalized, SORT_NUMERIC);
        return array_values($normalized);
    }

    /** @param list<int> $nodeIds */
    private function assertNodesBelongToSite(int $siteId, array $nodeIds): void
    {
        if ($nodeIds === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($nodeIds), '?'));
        $statement = $this->db->prepare(
            sprintf('SELECT COUNT(*) FROM nodes WHERE site_id = ? AND id IN (%s)', $placeholders),
        );
        $statement->execute([$siteId, ...$nodeIds]);

        if ((int) $statement->fetchColumn() !== count($nodeIds)) {
            throw new RuntimeException('One or more nodes do not belong to the selected site.');
        }
    }
}
