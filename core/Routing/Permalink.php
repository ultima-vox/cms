<?php

declare(strict_types=1);

namespace Core\Routing;

use RuntimeException;

final readonly class Permalink
{
    public string $nodePath;

    public function __construct(string $nodePath, public string $style = 'clean')
    {
        if (!in_array($style, ['clean', 'html'], true)) {
            throw new RuntimeException('Unsupported permalink style.');
        }
        if (
            $nodePath === '' || $nodePath[0] !== '/' || strlen($nodePath) > 2048
            || str_contains($nodePath, '//') || $nodePath !== trim($nodePath)
        ) {
            throw new RuntimeException('Invalid permalink node path.');
        }
        $this->nodePath = rtrim($nodePath, '/') ?: '/';
        foreach (explode('/', ltrim($this->nodePath, '/')) as $segment) {
            if ($this->nodePath === '/') {
                break;
            }
            if (
                $segment === '.' || $segment === '..'
                || preg_match('/^[A-Za-z0-9._~-]{1,255}$/D', $segment) !== 1
            ) {
                throw new RuntimeException('Permalink contains an unsafe path segment.');
            }
        }
        if (SystemPathPolicy::isReserved($this->nodePath)) {
            throw new RuntimeException('System paths cannot be published as content permalinks.');
        }
        if ($style === 'html' && $this->nodePath !== '/') {
            if ($this->nodePath === '/index' || strlen(basename($this->nodePath)) > 250) {
                throw new RuntimeException('HTML permalink collides with the site index or exceeds the filename limit.');
            }
        }
    }

    public function url(): string
    {
        if ($this->nodePath === '/') {
            return '/';
        }
        return $this->nodePath . ($this->style === 'html' ? '.html' : '/');
    }

    public function relativeFile(): string
    {
        if ($this->nodePath === '/') {
            return 'index.html';
        }
        return ltrim($this->nodePath, '/') . ($this->style === 'html' ? '.html' : '/index.html');
    }
}
