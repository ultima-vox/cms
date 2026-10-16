<?php

declare(strict_types=1);

namespace Core\Delivery\Cache;

use RuntimeException;

final class FilesystemTagIndex
{
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * Replace tag edges and publish the cache value under one mutation lock.
     *
     * @param list<string> $tags
     * @param callable():void $publish
     */
    public function publish(
        string $key,
        array $tags,
        callable $publish,
        ?string $expectedGeneration = null,
        ?int $expiresAt = null,
    ): void {
        $key = $this->key($key);
        $tags = $this->tags($tags);

        $this->locked(function () use ($key, $tags, $publish, $expectedGeneration, $expiresAt): void {
            if ($expectedGeneration !== null && $expectedGeneration !== $this->currentGeneration()) {
                throw new RuntimeException('Delivery generation changed; retry rendering with fresh data.');
            }
            if ($expiresAt !== null && $expiresAt <= time()) {
                throw new RuntimeException('Delivery output expired before publication.');
            }
            $old = $this->readJson($this->keyPath($key));
            $this->advanceGeneration();
            try {
                $this->replaceMetadata($key, $tags, $expiresAt);
                $publish();
            } catch (\Throwable $exception) {
                // Atomic writers retain the old value on failure; retain its invalidation edges too.
                $this->replaceMetadata(
                    $key,
                    is_array($old['tags'] ?? null) ? $old['tags'] : [],
                    is_int($old['expires_at'] ?? null) ? $old['expires_at'] : null,
                );
                throw $exception;
            }
        });
    }

    public function generation(): string
    {
        return $this->locked(fn (): string => $this->currentGeneration());
    }

    /** @param callable():?string $read @param callable():void $remove */
    public function readFresh(string $key, callable $read, callable $remove): ?string
    {
        $key = $this->key($key);
        return $this->locked(function () use ($key, $read, $remove): ?string {
            $data = $this->readJson($this->keyPath($key));
            if (($data['key'] ?? null) !== $key || !is_int($data['expires_at'] ?? null)) {
                return null;
            }
            if ($data['expires_at'] <= time()) {
                $remove();
                $this->replaceMetadata($key, [], null);
                return null;
            }
            return $read();
        });
    }

    /** @param callable(string):void $remove */
    public function pruneExpired(callable $remove): int
    {
        return $this->locked(function () use ($remove): int {
            $count = 0;
            foreach (glob(rtrim($this->directory, '/') . '/keys/*/*.json') ?: [] as $path) {
                $data = $this->readJson($path);
                if (
                    !is_int($data['expires_at'] ?? null) || $data['expires_at'] > time()
                    || !is_string($data['key'] ?? null)
                ) {
                    continue;
                }
                $key = $this->key($data['key']);
                if ($this->keyPath($key) !== $path) {
                    continue;
                }
                $remove($key);
                $this->replaceMetadata($key, [], null);
                $count++;
            }
            return $count;
        });
    }

    /** @param callable():void $remove */
    public function remove(string $key, callable $remove, ?string $expectedGeneration = null): void
    {
        $key = $this->key($key);

        $this->locked(function () use ($key, $remove, $expectedGeneration): void {
            if ($expectedGeneration !== null && $expectedGeneration !== $this->currentGeneration()) {
                return;
            }
            $this->advanceGeneration();
            $remove();
            foreach ($this->readKeyTags($key) as $tag) {
                $this->removeKeyFromTag($tag, $key);
            }
            $this->deleteKeyFile($key);
        });
    }

    /**
     * @param list<string> $tags
     * @param callable(string):void $remove
     */
    public function invalidate(array $tags, callable $remove): int
    {
        $tags = $this->tags($tags);
        if ($tags === []) {
            return 0;
        }

        return $this->locked(function () use ($tags, $remove): int {
            // Advance even with no indexed artifacts: a first publication may still be rendering.
            $this->advanceGeneration();
            $keys = [];
            foreach ($tags as $tag) {
                foreach ($this->readTagKeys($tag) as $key) {
                    $keys[$key] = true;
                }
            }

            foreach (array_keys($keys) as $key) {
                $remove($key);
                foreach ($this->readKeyTags($key) as $tag) {
                    $this->removeKeyFromTag($tag, $key);
                }
                $this->deleteKeyFile($key);
            }

            return count($keys);
        });
    }

    private function currentGeneration(): string
    {
        $data = $this->readJson($this->directory . '/generation.json');
        if (!is_string($data['token'] ?? null) || preg_match('/^[a-f0-9]{32}$/D', $data['token']) !== 1) {
            return $this->advanceGeneration();
        }
        return $data['token'];
    }

    private function advanceGeneration(): string
    {
        $token = bin2hex(random_bytes(16));
        $this->writeJson($this->directory . '/generation.json', ['token' => $token]);
        return $token;
    }

    /** @param list<string> $tags */
    private function replaceMetadata(string $key, array $tags, ?int $expiresAt): void
    {
        $old = $this->readKeyTags($key);
        foreach (array_diff($old, $tags) as $tag) {
            $this->removeKeyFromTag($tag, $key);
        }
        foreach ($tags as $tag) {
            $this->addKeyToTag($tag, $key);
        }
        if ($tags === []) {
            $this->deleteKeyFile($key);
            return;
        }
        $this->writeJson($this->keyPath($key), [
            'key' => $key,
            'tags' => $tags,
            'expires_at' => $expiresAt,
        ]);
    }

    /** @template T @param callable():T $callback @return T */
    private function locked(callable $callback): mixed
    {
        $this->ensureDirectory($this->directory);
        $lockPath = rtrim($this->directory, '/') . '/index.lock';
        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open cache tag index lock.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock cache tag index.');
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return list<string> */
    private function readKeyTags(string $key): array
    {
        $data = $this->readJson($this->keyPath($key));
        if (($data['key'] ?? null) !== $key || !is_array($data['tags'] ?? null)) {
            return [];
        }

        return array_values(array_filter($data['tags'], 'is_string'));
    }

    /** @return list<string> */
    private function readTagKeys(string $tag): array
    {
        $data = $this->readJson($this->tagPath($tag));
        if (($data['tag'] ?? null) !== $tag || !is_array($data['keys'] ?? null)) {
            return [];
        }

        return array_values(array_filter($data['keys'], 'is_string'));
    }

    private function addKeyToTag(string $tag, string $key): void
    {
        $keys = array_fill_keys($this->readTagKeys($tag), true);
        $keys[$key] = true;
        $this->writeJson($this->tagPath($tag), [
            'tag' => $tag,
            'keys' => array_keys($keys),
        ]);
    }

    private function removeKeyFromTag(string $tag, string $key): void
    {
        $keys = array_fill_keys($this->readTagKeys($tag), true);
        unset($keys[$key]);
        $path = $this->tagPath($tag);

        if ($keys === []) {
            if (is_file($path)) {
                @unlink($path);
            }
            return;
        }

        $this->writeJson($path, [
            'tag' => $tag,
            'keys' => array_keys($keys),
        ]);
    }

    private function deleteKeyFile(string $key): void
    {
        $path = $this->keyPath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $this->ensureDirectory(dirname($path));
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $temp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';

        if (file_put_contents($temp, $json, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Unable to write cache index file: %s.', $temp));
        }

        @chmod($temp, 0664);
        if (!@rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException(sprintf('Unable to publish cache index file: %s.', $path));
        }
    }

    private function keyPath(string $key): string
    {
        $hash = hash('sha256', $key);
        return rtrim($this->directory, '/') . '/keys/' . substr($hash, 0, 2) . '/' . $hash . '.json';
    }

    private function tagPath(string $tag): string
    {
        $hash = hash('sha256', $tag);
        return rtrim($this->directory, '/') . '/tags/' . substr($hash, 0, 2) . '/' . $hash . '.json';
    }

    /** @param list<string> $tags @return list<string> */
    private function tags(array $tags): array
    {
        $result = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag === '' || strlen($tag) > 256 || preg_match('/[\x00-\x1F\x7F]/', $tag) === 1) {
                throw new RuntimeException('Invalid cache tag.');
            }
            $result[$tag] = true;
        }

        return array_keys($result);
    }

    private function key(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new RuntimeException('Invalid cache key.');
        }

        return $key;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create cache index directory: %s.', $directory));
        }
    }
}
