<?php

declare(strict_types=1);

namespace Core\Delivery\StaticPage;

use Core\Delivery\Cache\FilesystemTagIndex;
use RuntimeException;

final readonly class StaticPagePublisher
{
    private FilesystemTagIndex $index;

    public function __construct(
        private string $rootDirectory,
        string $indexDirectory,
    ) {
        $this->index = new FilesystemTagIndex($indexDirectory);
    }

    /** @param list<string> $dependencies */
    public function publish(int $siteId, string $path, string $html, array $dependencies): string
    {
        $relative = $this->relativePath($siteId, $path);
        $key = 'static:' . $relative;
        $target = rtrim($this->rootDirectory, '/') . '/' . $relative;

        $this->index->publish(
            $key,
            $dependencies,
            function () use ($target, $html): void {
                $directory = dirname($target);
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException(sprintf('Unable to create static page directory: %s.', $directory));
                }

                $temp = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
                if (file_put_contents($temp, $html, LOCK_EX) === false) {
                    throw new RuntimeException(sprintf('Unable to write static page: %s.', $temp));
                }
                @chmod($temp, 0664);
                if (!@rename($temp, $target)) {
                    @unlink($temp);
                    throw new RuntimeException(sprintf('Unable to publish static page: %s.', $target));
                }
            },
        );

        return $target;
    }

    public function delete(int $siteId, string $path): void
    {
        $relative = $this->relativePath($siteId, $path);
        $target = rtrim($this->rootDirectory, '/') . '/' . $relative;
        $key = 'static:' . $relative;

        $this->index->remove($key, function () use ($target): void {
            if (is_file($target)) {
                @unlink($target);
            }
        });
    }

    /** @param list<string> $dependencies */
    public function invalidate(array $dependencies): int
    {
        $root = rtrim($this->rootDirectory, '/') . '/';

        return $this->index->invalidate(
            $dependencies,
            static function (string $key) use ($root): void {
                if (!str_starts_with($key, 'static:')) {
                    return;
                }
                $relative = substr($key, 7);
                if ($relative === false || $relative === '' || str_contains($relative, '..')) {
                    return;
                }
                $target = $root . $relative;
                if (is_file($target)) {
                    @unlink($target);
                }
            },
        );
    }

    public function path(int $siteId, string $path): string
    {
        return rtrim($this->rootDirectory, '/') . '/' . $this->relativePath($siteId, $path);
    }

    private function relativePath(int $siteId, string $path): string
    {
        if ($siteId < 1) {
            throw new RuntimeException('Site id must be positive.');
        }

        $path = trim($path);
        if ($path === '' || $path[0] !== '/' || strlen($path) > 2048 || str_contains($path, "\0")) {
            throw new RuntimeException('Invalid static page path.');
        }

        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn (string $part): bool => $part !== '',
        ));
        foreach ($segments as $segment) {
            if ($segment === '.'
                || $segment === '..'
                || preg_match('/^[A-Za-z0-9._~-]{1,255}$/D', $segment) !== 1) {
                throw new RuntimeException('Static page path contains an unsafe segment.');
            }
        }

        $relative = 'sites/' . $siteId . '/';
        if ($segments !== []) {
            $relative .= implode('/', $segments) . '/';
        }

        return $relative . 'index.html';
    }
}
