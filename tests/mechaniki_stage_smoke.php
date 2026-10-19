<?php

declare(strict_types=1);

use Core\Database;
use Core\Repository\SiteRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$layout = file_get_contents($root . '/templates/layouts/mechaniki.php');
$config = file_get_contents($root . '/public/assets/mechaniki/site-config.js');
if (!is_string($layout) || !is_string($config)
    || !str_contains($layout, '$page->execute()')
    || !str_contains($layout, 'noindex,nofollow')
    || str_contains($layout, 'Core_')
    || str_contains($layout, 'href="/mechaniki')
    || !str_contains($config, 'bookingEndpoint:null')
    || !str_contains($config, 'bookingTokenEndpoint:null')) {
    throw new RuntimeException('Mechaniki staging layout or disabled booking contract is invalid.');
}

foreach (['home.css', 'commercial.css', 'internal.css', 'motion.css', 'release.css',
          'site-config.js', 'commercial.js', 'internal.js', 'motion.js',
          'mobile-drawer.js', 'services-experiment.js', 'icons.svg',
          'manrope.woff2', 'workshop.webp', 'production.css', 'production.js'] as $asset) {
    if (!is_file($root . '/public/assets/mechaniki/' . $asset)) {
        throw new RuntimeException('Missing Mechaniki asset: ' . $asset);
    }
}

$dir = $root . '/sites/mechaniki/pages';
$pages = [];
foreach (new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
) as $entry) {
    if ($entry->isFile() && $entry->getExtension() === 'html') {
        $pages[] = $entry->getPathname();
        $html = file_get_contents($entry->getPathname());
        if (!is_string($html) || str_contains($html, '<?') || str_contains($html, 'Core_')
            || str_contains($html, 'https://mechaniki.ru/assets/mechaniki/')
            || str_contains($html, 'https://mechaniki.rewired-ag.chatgpt.site/')) {
            throw new RuntimeException('Unsafe legacy content: ' . $entry->getPathname());
        }
        if (preg_match_all('/<button\b[^>]*type="submit"[^>]*>/i', $html, $matches)) {
            foreach ($matches[0] as $button) {
                if (!str_contains($button, 'disabled')) {
                    throw new RuntimeException('Staging submission is enabled: ' . $entry->getPathname());
                }
            }
        }
    }
}
if (count($pages) !== 56) {
    throw new RuntimeException('Expected 56 Mechaniki content pages, got ' . count($pages));
}

$db = Database::connection();
if ((new SiteRepository($db))->findActiveByHost('ru.rewired.ru') !== null) {
    throw new RuntimeException('CI staging host unexpectedly exists already.');
}

$siteId = null;
try {
    $insert = $db->query(
        "INSERT INTO sites (code, name) VALUES ('mechaniki-stage-smoke', 'Mechaniki smoke') RETURNING id"
    );
    $siteId = (int) $insert->fetchColumn();
    $domain = $db->prepare(
        'INSERT INTO site_domains (site_id, host, is_primary) VALUES (:site_id, :host, TRUE)'
    );
    $domain->execute(['site_id' => $siteId, 'host' => 'ru.rewired.ru']);

    $command = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg($root . '/sites/mechaniki/activate.php') . ' --apply';
    exec($command . ' 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException('Mechaniki activation failed: ' . implode("\n", $output));
    }

    $query = $db->prepare(
        <<<'SQL'
        SELECT COUNT(*)
        FROM nodes n
        JOIN layouts l ON l.id = n.layout_id AND l.site_id = n.site_id
        WHERE n.site_id = :site_id
          AND n.page_type = 'documents.page'
          AND l.code = 'mechaniki'
          AND n.status = 'published'
        SQL
    );
    $query->execute(['site_id' => $siteId]);
    if ((int) $query->fetchColumn() !== 56) {
        throw new RuntimeException('Mechaniki content pages were not activated as CMS nodes.');
    }

    $rootNode = $db->prepare('SELECT page_config FROM nodes WHERE site_id = :site_id AND path = :path');
    $rootNode->execute(['site_id' => $siteId, 'path' => '/']);
    $config = json_decode((string) $rootNode->fetchColumn(), true, flags: JSON_THROW_ON_ERROR);
    if (($config['document'] ?? null) !== 'mechaniki-home') {
        throw new RuntimeException('Mechaniki root is not linked to its Documents page.');
    }

    $versions = $db->prepare(
        <<<'SQL'
        SELECT COUNT(*)
        FROM document_versions v
        JOIN documents d ON d.id = v.document_id
        WHERE d.site_id = :site_id AND v.status = 'published'
        SQL
    );
    $versions->execute(['site_id' => $siteId]);
    if ((int) $versions->fetchColumn() !== 56) {
        throw new RuntimeException('Mechaniki published page versions are incomplete.');
    }

    $output = [];
    exec($command . ' 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException('Mechaniki repeat activation failed: ' . implode("\n", $output));
    }
    $versions->execute(['site_id' => $siteId]);
    if ((int) $versions->fetchColumn() !== 56) {
        throw new RuntimeException('Repeat staging activation created duplicate versions.');
    }
} finally {
    if ($siteId !== null) {
        foreach (['nodes', 'documents', 'site_domains', 'layouts'] as $table) {
            $delete = $db->prepare('DELETE FROM ' . $table . ' WHERE site_id = :site_id');
            $delete->execute(['site_id' => $siteId]);
        }
        $deleteSite = $db->prepare('DELETE FROM sites WHERE id = :id');
        $deleteSite->execute(['id' => $siteId]);
    }
}

fwrite(STDOUT, "MECHANIKI CMS STAGING OK\n");
