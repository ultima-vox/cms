<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem\Admin;

use Core\Repository\LayoutRepository;

/**
 * Only public, local CSS files referenced by layouts owned by the active site
 * may be loaded in a sandboxed editor preview. No editor input becomes a URL.
 */
final readonly class SiteStylesheetResolver
{
    public function __construct(
        private string $rootPath,
        private LayoutRepository $layouts,
    ) {
    }

    /** @return list<string> */
    public function resolve(): array
    {
        $styles = [];
        $layouts = $this->layouts->all();
        usort($layouts, static fn (array $a, array $b): int
            => (int) ($b['node_count'] ?? 0) <=> (int) ($a['node_count'] ?? 0));
        foreach ($layouts as $layout) {
            $path = (string) ($layout['template_path'] ?? '');
            if (!preg_match('#^layouts/[a-z0-9_-]+(?:\\.html)?\\.php$#D', $path)) {
                continue;
            }
            $file = $this->rootPath . '/templates/' . $path;
            if (!is_file($file) || filesize($file) > 500000) {
                continue;
            }
            $html = file_get_contents($file);
            if (!is_string($html)) {
                continue;
            }
            preg_match_all(
                '#<link\\b[^>]*\\brel=["\\x27]stylesheet["\\x27][^>]*\\bhref=["\\x27](/assets/[a-zA-Z0-9_./-]+\\.css)["\\x27][^>]*>#i',
                $html,
                $matches,
            );
            foreach ($matches[1] as $url) {
                if (str_contains($url, '..') || !is_file($this->rootPath . '/public' . $url)) {
                    continue;
                }
                $styles[$url] = true;
                if (count($styles) >= 8) {
                    break 2;
                }
            }
        }
        return array_keys($styles);
    }
}
