<?php

declare(strict_types=1);

namespace Core\Extension;

use PDO;
use PDOException;
use RuntimeException;

final readonly class ModuleStateRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @param list<ModuleManifest> $manifests */
    public function sync(array $manifests): int
    {
        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO installed_modules (code, name, version, extension_api, is_enabled)
            VALUES (:code, :name, :version, :extension_api, :is_enabled)
            ON CONFLICT (code) DO UPDATE SET
                name = EXCLUDED.name,
                version = EXCLUDED.version,
                extension_api = EXCLUDED.extension_api,
                updated_at = CURRENT_TIMESTAMP
            SQL
        );

        foreach ($manifests as $manifest) {
            $statement->bindValue(':code', $manifest->code, PDO::PARAM_STR);
            $statement->bindValue(':name', $manifest->name, PDO::PARAM_STR);
            $statement->bindValue(':version', $manifest->version, PDO::PARAM_STR);
            $statement->bindValue(':extension_api', $manifest->extensionApi, PDO::PARAM_STR);
            $statement->bindValue(':is_enabled', $manifest->defaultEnabled, PDO::PARAM_BOOL);
            $statement->execute();
        }

        return count($manifests);
    }

    public function isEnabled(ModuleManifest $manifest): bool
    {
        try {
            $statement = $this->db->prepare(
                'SELECT is_enabled FROM installed_modules WHERE code = :code LIMIT 1'
            );
            $statement->execute(['code' => $manifest->code]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '42P01') {
                return $manifest->defaultEnabled;
            }
            throw $exception;
        }

        if ($row === false || !array_key_exists('is_enabled', $row)) {
            return $manifest->defaultEnabled;
        }

        return $this->boolean($row['is_enabled']);
    }

    public function setEnabled(string $code, bool $enabled): void
    {
        $statement = $this->db->prepare(
            'UPDATE installed_modules SET is_enabled = :enabled, updated_at = CURRENT_TIMESTAMP WHERE code = :code'
        );
        $statement->bindValue(':enabled', $enabled, PDO::PARAM_BOOL);
        $statement->bindValue(':code', $code, PDO::PARAM_STR);
        $statement->execute();

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(sprintf(
                'Module "%s" is not synchronized. Run extensions:sync first.',
                $code,
            ));
        }
    }

    public function remove(string $code): void
    {
        $statement = $this->db->prepare('DELETE FROM installed_modules WHERE code = :code');
        $statement->execute(['code' => $code]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(sprintf(
                'Module "%s" is not synchronized.',
                $code,
            ));
        }
    }

    /** @return array<string, array{code:string,name:string,version:string,extension_api:string,is_enabled:bool}> */
    public function all(): array
    {
        try {
            $rows = $this->db->query(
                'SELECT code, name, version, extension_api, is_enabled FROM installed_modules ORDER BY code'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '42P01') {
                return [];
            }
            throw $exception;
        }

        $result = [];
        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $result[$code] = [
                'code' => $code,
                'name' => (string) $row['name'],
                'version' => (string) $row['version'],
                'extension_api' => (string) $row['extension_api'],
                'is_enabled' => $this->boolean($row['is_enabled']),
            ];
        }

        return $result;
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 't', 'true', 'yes', 'on'], true);
    }
}
