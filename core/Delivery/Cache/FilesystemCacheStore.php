<?php

declare(strict_types=1);

namespace Core\Delivery\Cache;

use RuntimeException;

final class FilesystemCacheStore implements CacheStoreInterface
{
    public function __construct(private readonly string $directory)
    {
    }

    public function get(string $key): ?string
    {
        $key = $this->key($key);
        $path = $this->path($key);
        if (!is_file($path)) {
            return null;
        }

        $payload = @file_get_contents($path);
        if (!is_string($payload)) {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->delete($key);
            return null;
        }

        if (!is_array($decoded)
            || ($decoded['key'] ?? null) !== $key
            || !is_string($decoded['value'] ?? null)) {
            $this->delete($key);
            return null;
        }

        $expiresAt = $decoded['expires_at'] ?? null;
        if ($expiresAt !== null && (!is_int($expiresAt) || $expiresAt <= time())) {
            $this->delete($key);
            return null;
        }

        $value = base64_decode($decoded['value'], true);
        if (!is_string($value)) {
            $this->delete($key);
            return null;
        }

        return $value;
    }

    public function put(string $key, string $value, ?int $ttlSeconds = null): void
    {
        $key = $this->key($key);
        if ($ttlSeconds !== null && $ttlSeconds < 1) {
            throw new RuntimeException('Cache TTL must be at least one second.');
        }

        $path = $this->path($key);
        $this->ensureDirectory(dirname($path));

        $payload = json_encode([
            'key' => $key,
            'expires_at' => $ttlSeconds === null ? null : time() + $ttlSeconds,
            'value' => base64_encode($value),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $temp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temp, $payload, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Unable to write cache file: %s.', $temp));
        }

        @chmod($temp, 0664);
        if (!@rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException(sprintf('Unable to publish cache file: %s.', $path));
        }
    }

    public function delete(string $key): void
    {
        $path = $this->path($this->key($key));
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function key(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new RuntimeException('Invalid cache key.');
        }

        return $key;
    }

    private function path(string $key): string
    {
        $hash = hash('sha256', $key);
        return rtrim($this->directory, '/') . '/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . '.json';
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }
        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create cache directory: %s.', $directory));
        }
    }
}
