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

                SQL . \Core\Database\SqlDialect::upsert($this->db, ['code'], ['name']) . \Core\Database\SqlDialect::returningId($this->db));
            $roleStatement = $this->db->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
            $assignmentStatement = $this->db->prepare(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)' . \Core\Database\SqlDialect::upsert($this->db, ['role_id', 'permission_id'])
            );

            foreach ($definitions as $definition) {
                $permissionStatement->execute([
                    'code' => $definition->code,
                    'name' => $definition->name,
                ]);
                if (\Core\Database\SqlDialect::mysql($this->db)) {
                    $lookup = $this->db->prepare('SELECT id FROM permissions WHERE code = :code');
                    $lookup->execute(['code' => $definition->code]);
                    $permissionId = (int) $lookup->fetchColumn();
                } else {
                    $permissionId = (int) $permissionStatement->fetchColumn();
                }

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
