<?php

declare(strict_types=1);

namespace Core\Extension;

use PDO;
use PDOException;
use RuntimeException;

final class ModuleStateRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @param array<string, ModuleManifest> $manifests */
    public function sync(array $manifests): int
    {
        $count = 0;
        $statement = $this->db->prepare(
            <<<'SQL'
            INSERT INTO installed_modules (code, name, version, is_enabled)
            VALUES (:code, :name, :version, :is_enabled)
            ON CONFLICT (code) DO UPDATE SET
                name = EXCLUDED.name,
                version = EXCLUDED.version,
                updated_at = CURRENT_TIMESTAMP
            SQL,
        );

        foreach ($manifests as $manifest) {
            $statement->bindValue(':code', $manifest->code, PDO::PARAM_STR);
            $statement->bindValue(':name', $manifest->name, PDO::PARAM_STR);
            $statement->bindValue(':version', $manifest->version, PDO::PARAM_STR);
            $statement->bindValue(':is_enabled', $manifest->defaultEnabled, PDO::PARAM_BOOL);
            $statement->execute();
            ++$count;
        }

        return $count;
    }

    public function isEnabled(ModuleManifest $manifest): bool
    {
        try {
            $statement = $this->db->prepare(
                'SELECT is_enabled FROM installed_modules WHERE code = :code LIMIT 1',
            );
            $statement->execute(['code' => $manifest->code]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return $manifest->defaultEnabled;
        }

        if ($row === false || !array_key_exists('is_enabled', $row)) {
            return $manifest->defaultEnabled;
        }

        return $this->normalizeBoolean($row['is_enabled']);
    }

    public function setEnabled(string $code, bool $enabled): void
    {
        $statement = $this->db->prepare(
            'UPDATE installed_modules SET is_enabled = :enabled, updated_at = CURRENT_TIMESTAMP WHERE code = :code',
        );
        $statement->bindValue(':enabled', $enabled, PDO::PARAM_BOOL);
        $statement->bindValue(':code', $code, PDO::PARAM_STR);
        $statement->execute();

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(sprintf('Module "%s" is not synchronized. Run extensions:sync first.', $code));
        }
    }

    /** @return array<string, array{code:string,name:string,version:string,is_enabled:bool}> */
    public function all(): array
    {
        try {
            $rows = $this->db->query(
                'SELECT code, name, version, is_enabled FROM installed_modules ORDER BY code ASC',
            )->fetchAll();
        } catch (PDOException) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $result[$code] = [
                'code' => $code,
                'name' => (string) $row['name'],
                'version' => (string) $row['version'],
                'is_enabled' => $this->normalizeBoolean($row['is_enabled']),
            ];
        }

        return $result;
    }

    private function normalizeBoolean(mixed $value): bool
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
