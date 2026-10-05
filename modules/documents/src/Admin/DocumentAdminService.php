<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents\Admin;

use PDO;
use RuntimeException;
use Throwable;
use UltimaVox\Modules\Documents\Repository\DocumentManagementRepository;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

final readonly class DocumentAdminService
{
    public function __construct(
        private PDO $db,
        private int $siteId,
        private DocumentRepository $documents,
        private DocumentManagementRepository $management,
    ) {
        if ($siteId < 1) {
            throw new RuntimeException('Documents admin service requires a positive site id.');
        }
    }

    public function create(
        string $code,
        string $name,
        string $content,
        bool $publish,
    ): int {
        return $this->transaction(function () use ($code, $name, $content, $publish): int {
            $documentId = $this->documents->create($this->siteId, $code, $name);
            $versionId = $this->documents->createDraftVersion($documentId, $content);
            if ($publish) {
                $this->documents->publishVersion($documentId, $versionId);
            }

            return $documentId;
        });
    }

    public function save(
        int $documentId,
        string $name,
        string $content,
        bool $publish,
    ): int {
        return $this->transaction(function () use ($documentId, $name, $content, $publish): int {
            if ($this->management->find($documentId) === null) {
                throw new RuntimeException('Document was not found for the current site.');
            }

            $this->management->updateName($documentId, $name);
            $versionId = $this->documents->createDraftVersion($documentId, $content);
            if ($publish) {
                $this->documents->publishVersion($documentId, $versionId);
            }

            return $versionId;
        });
    }

    /** @template T @param callable():T $callback @return T */
    private function transaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();
            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
