<?php

declare(strict_types=1);
namespace Core\Extension;

use PDO;
use Throwable;

/** One deployment-wide metadata reconciliation using the newly loaded PHP classes. */
final readonly class PendingExtensionSync
{
    public function __construct(private PDO $db, private string $root) {}
    public function run(): void
    {
        if ($this->db->query('SELECT 1 FROM extension_sync_jobs LIMIT 1')->fetchColumn() === false) return;
        $this->db->beginTransaction();
        try {
            $this->db->query("SELECT pg_advisory_xact_lock(hashtext('extension-metadata-sync'))");
            if ($this->db->query('SELECT 1 FROM extension_sync_jobs LIMIT 1')->fetchColumn() !== false) {
                (new ModuleManager($this->db, $this->root))->sync();
                $this->db->exec('DELETE FROM extension_sync_jobs');
            }
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
