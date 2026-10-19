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

        $release = json_decode((string) @file_get_contents($rootPath . '/release.json'), true);
        $layout = $release['layout'] ?? null;
        $replace = [];
        foreach ($data['assets'] as $asset) {
            if (!is_array($asset)
                || !is_string($asset['source_url'] ?? null)
                || !is_string($asset['target'] ?? null)
                || !preg_match('#^/assets/mechaniki/legacy/[a-zA-Z0-9_-]+\.(?:jpe?g|png)$#D', $asset['target'])) {
                continue;
            }

            // Never substitute a local URL until its file really exists.
            $privateFile = $rootPath . '/public' . $asset['target'];
            if (!is_file($privateFile)) {
                continue;
            }

            if ($layout === 'webroot'
                && !is_file(dirname($rootPath) . $asset['target'])) {
                continue;
            }

            $replace[$asset['source_url']] = $asset['target'];
        }

        // The old static export references diagnostic.jpeg on many pages,
        // but that binary was not included in the signed release.
        // Use an explicit local fallback until the original can be imported.
        $diagnostic = '/assets/mechaniki/diagnostic.jpeg';
        $fallback = '/assets/mechaniki/workshop.webp';
        $hasDiagnostic = is_file($rootPath . '/public' . $diagnostic)
            && ($layout !== 'webroot' || is_file(dirname($rootPath) . $diagnostic));
        if (!$hasDiagnostic
            && is_file($rootPath . '/public' . $fallback)
            && ($layout !== 'webroot' || is_file(dirname($rootPath) . $fallback))) {
            $replace[$diagnostic] = $fallback;
        }

        return $replace === [] ? $html : strtr($html, $replace);
    }
}
