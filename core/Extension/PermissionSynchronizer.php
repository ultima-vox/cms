<?php

declare(strict_types=1);

namespace Core\Extension;

use Core\Extension\Api\PermissionsApi;
use PDO;
use RuntimeException;
use Throwable;

final readonly class PermissionSynchronizer
{
    public function __construct(private PDO $db)
    {
    }

    public function sync(PermissionsApi $permissions): int
    {
        $definitions = $permissions->definitions();
        if ($definitions === []) {
            return 0;
        }

        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $permissionStatement = $this->db->prepare(
                <<<'SQL'
                INSERT INTO permissions (code, name)
                VALUES (:code, :name)
                ON CONFLICT (code) DO UPDATE
                SET name = EXCLUDED.name
                RETURNING id
                SQL
            );
            $roleStatement = $this->db->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
            $assignmentStatement = $this->db->prepare(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id) ON CONFLICT DO NOTHING'
            );

            foreach ($definitions as $definition) {
                $permissionStatement->execute([
                    'code' => $definition->code,
                    'name' => $definition->name,
                ]);
                $permissionId = (int) $permissionStatement->fetchColumn();

                foreach ($definition->defaultRoles as $roleCode) {
                    $roleStatement->execute(['code' => $roleCode]);
                    $roleId = $roleStatement->fetchColumn();

                    if ($roleId === false) {
                        throw new RuntimeException(sprintf(
                            'Default role "%s" for permission "%s" is missing.',
                            $roleCode,
                            $definition->code,
                        ));
                    }

                    $assignmentStatement->execute([
                        'role_id' => (int) $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }

            if ($startedTransaction) {
                $this->db->commit();
            }

            return count($definitions);
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }
}
