<?php

declare(strict_types=1);

namespace Core\Delivery\StaticPage;

use Core\Delivery\Cache\FilesystemTagIndex;
use Core\Routing\Permalink;
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
    public function publish(
        int $siteId,
        string $path,
        string $html,
        array $dependencies,
        string $permalinkStyle = 'clean',
    ): string
    {
        $relative = $this->relativePath($siteId, $path, $permalinkStyle);
        $key = 'static:' . $relative;
        $target = $this->targetPath($relative);

        $dependencies[] = 'site:' . $siteId;
        $this->index->publish(
            $key,
            array_values(array_unique($dependencies)),
            function () use ($relative, $target, $html): void {
                $this->targetPath($relative, true);

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

    public function delete(int $siteId, string $path, string $permalinkStyle = 'clean'): void
    {
        $relative = $this->relativePath($siteId, $path, $permalinkStyle);
        $target = $this->targetPath($relative);
        $key = 'static:' . $relative;

        $this->index->remove($key, function () use ($target): void {
            $this->removeFile($target);
        });
    }

    /** @param list<string> $dependencies */
    public function invalidate(array $dependencies): int
    {
        return $this->index->invalidate(
            $dependencies,
            function (string $key): void {
                if (!str_starts_with($key, 'static:')) {
                    return;
                }
                $relative = substr($key, 7);
                if ($relative === false
                    || preg_match('#^sites/[1-9][0-9]*/[A-Za-z0-9._~/-]+$#D', $relative) !== 1
                    || in_array('..', explode('/', $relative), true)
                    || in_array('.', explode('/', $relative), true)) {
                    return;
                }
                $this->removeFile($this->targetPath($relative));
            },
        );
    }

    public function path(int $siteId, string $path, string $permalinkStyle = 'clean'): string
    {
        return $this->targetPath($this->relativePath($siteId, $path, $permalinkStyle));
    }

    private function relativePath(int $siteId, string $path, string $permalinkStyle): string
    {
        if ($siteId < 1) {
            throw new RuntimeException('Site id must be positive.');
        }
        return 'sites/' . $siteId . '/' . (new Permalink($path, $permalinkStyle))->relativeFile();
    }

    private function targetPath(string $relative, bool $createDirectories = false): string
    {
        $root = rtrim($this->rootDirectory, '/\\');
        if ($root === '' || is_link($root)) {
            throw new RuntimeException('Static output root is empty or a symbolic link.');
        }
        $directory = $root;
        $directories = array_merge([''], explode('/', dirname($relative)));
        foreach ($directories as $segment) {
            if ($segment !== '') {
                $directory .= '/' . $segment;
            }
            if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
                throw new RuntimeException('Static output path contains a symbolic link or non-directory.');
            }
            if ($createDirectories && !is_dir($directory)
                && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create static output directory.');
            }
        }
        $target = $root . '/' . $relative;
        if (is_link($target) || (file_exists($target) && !is_file($target))) {
            throw new RuntimeException('Static output target is not a regular file.');
        }
        return $target;
    }

    private function removeFile(string $target): void
    {
        if (is_file($target) && !unlink($target)) {
            throw new RuntimeException('Unable to remove static output file: ' . $target);
        }
    }
}
