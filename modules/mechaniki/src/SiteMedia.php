<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Mechaniki;

final class SiteMedia
{
    public static function localizeHtml(string $html, string $rootPath): string
    {
        $manifestPath = $rootPath . '/modules/mechaniki/site/media-manifest.json';
        if (!is_file($manifestPath)) {
            return $html;
        }

        $data = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($data) || !is_array($data['assets'] ?? null)) {
            return $html;
        }

        $replace = [];
        foreach ($data['assets'] as $asset) {
            if (!is_array($asset)
                || !is_string($asset['source_url'] ?? null)
                || !is_string($asset['target'] ?? null)
                || !preg_match('#^/assets/mechaniki/legacy/[a-zA-Z0-9_-]+\.(?:jpe?g|png)$#D', $asset['target'])) {
                continue;
            }

            // Never substitute a local URL until its file really exists.
            if (is_file($rootPath . '/public' . $asset['target'])) {
                $replace[$asset['source_url']] = $asset['target'];
            }
        }

        return $replace === [] ? $html : strtr($html, $replace);
    }
}
