<?php

declare(strict_types=1);

namespace Core\Extension;

use PDO;

final readonly class ModulePackageInventoryRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function recordInstall(string $moduleCode, string $version, string $packageSha256): void
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO module_package_inventory (
                module_code,
                version,
                package_sha256,
                source,
                installed_at,
                updated_at
            ) VALUES (
                :module_code,
                :version,
                :package_sha256,
                'package',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            SQL
        );
        $statement->execute([
            'module_code' => $moduleCode,
            'version' => $version,
            'package_sha256' => $packageSha256,
        ]);
    }

    public function recordUpdate(string $moduleCode, string $version, string $packageSha256): void
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            UPDATE module_package_inventory
            SET version = :version,
                package_sha256 = :package_sha256,
                updated_at = CURRENT_TIMESTAMP
            WHERE module_code = :module_code
              AND source = 'package'
            SQL
        );
        $statement->execute([
            'module_code' => $moduleCode,
            'version' => $version,
            'package_sha256' => $packageSha256,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException(sprintf(
                'Module "%s" is not registered as an installer-managed package.',
                $moduleCode,
            ));
        }
    }

    /** @return array{module_code:string,version:string,package_sha256:string,source:string,installed_at:string,updated_at:string}|null */
    public function find(string $moduleCode): ?array
    {
        $statement = $this->db->prepare(
            'SELECT module_code, version, package_sha256, source, installed_at, updated_at FROM module_package_inventory WHERE module_code = :module_code'
        );
        $statement->execute(['module_code' => $moduleCode]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return list<array{module_code:string,version:string,package_sha256:string,source:string,installed_at:string,updated_at:string}> */
    public function all(): array
    {
        $rows = $this->db->query(
            'SELECT module_code, version, package_sha256, source, installed_at, updated_at FROM module_package_inventory ORDER BY module_code'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter($rows, 'is_array'));
    }
}
