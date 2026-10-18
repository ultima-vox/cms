<?php

declare(strict_types=1);

namespace Core\Http;

/** Early guard: deliberately independent of Composer, modules and the database. */
final class DeploymentGuard
{
    /** @var array<string, resource> */
    private static array $leases = [];

    public static function enter(string $root): bool
    {
        // Keep the original location so requests from the previous release share the lock.
        $directory = $root . '/storage/updates';
        if (!is_dir($directory)) @mkdir($directory, 0755, true);
        if (is_file($directory . '/maintenance.json')) return false;
        $lease = @fopen($directory . '/requests.lock', 'c');
        if ($lease === false) return false;
        if (!flock($lease, LOCK_SH | LOCK_NB)) { fclose($lease); return false; }
        // Check again after taking the lock; deployment may have finished or stopped meanwhile.
        if (is_file($directory . '/maintenance.json')) { fclose($lease); return false; }
        self::$leases[$root] = $lease;
        return true;
    }

    public static function held(string $root): bool
    {
        return isset(self::$leases[$root]);
    }

    /** The caller must acquire an exclusive lease before it can replace any code. */
    public static function release(string $root): void
    {
        if (isset(self::$leases[$root])) {
            flock(self::$leases[$root], LOCK_UN);
            fclose(self::$leases[$root]);
            unset(self::$leases[$root]);
        }
    }
}
