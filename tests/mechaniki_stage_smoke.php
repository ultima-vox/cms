<?php

declare(strict_types=1);

use Core\Database;
use Core\Repository\SiteRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$layout = file_get_contents($root . '/templates/layouts/mechaniki-live.php');
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
$moduleEnabled = $db->query("SELECT is_enabled FROM installed_modules WHERE code = 'mechaniki'")->fetchColumn();
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
          AND l.code = 'mechaniki-live'
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
    $formCount = $db->prepare(
        <<<'SQL'
        SELECT COUNT(*)
        FROM document_versions v
        JOIN documents d ON d.id = v.document_id
        WHERE d.site_id = :site_id
          AND v.status = 'published'
          AND v.content LIKE '%<form%'
          AND v.content NOT LIKE '%<form inert%'
        SQL
    );
    $formCount->execute(['site_id' => $siteId]);
    if ((int) $formCount->fetchColumn() !== 0) {
        throw new RuntimeException('Enabled forms were imported into staging.');
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

    // The site-specific view package must be explicitly enabled before
    // activating Infosystem-backed routes; other sites remain isolated.
    $db->exec("UPDATE installed_modules SET is_enabled = TRUE WHERE code = 'mechaniki'");
    $infosystemsCommand = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg($root . '/sites/mechaniki/activate-infosystems.php') . ' --apply';
    $output = [];
    exec($infosystemsCommand . ' 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException('Mechaniki Infosystem activation failed: ' . implode("\n", $output));
    }

    $systemQuery = $db->prepare(
        'SELECT COUNT(*) FROM infosystems WHERE site_id = :site_id AND code LIKE :prefix'
    );
    $systemQuery->execute(['site_id' => $siteId, 'prefix' => 'mechaniki_%']);
    if ((int) $systemQuery->fetchColumn() !== 6) {
        throw new RuntimeException('Mechaniki did not create all six Infosystems.');
    }

    $itemQuery = $db->prepare(
        <<<'SQL'
        SELECT COUNT(*)
        FROM infosystem_items i
        JOIN infosystems s ON s.id = i.infosystem_id
        WHERE s.site_id = :site_id AND s.code LIKE 'mechaniki_%'
        SQL
    );
    $itemQuery->execute(['site_id' => $siteId]);
    if ((int) $itemQuery->fetchColumn() !== 103) {
        throw new RuntimeException('Mechaniki Infosystem items are incomplete.');
    }

    $nodeQuery = $db->prepare(
        <<<'SQL'
        SELECT COUNT(*)
        FROM nodes
        WHERE site_id = :site_id
          AND page_type IN ('infosystem.list', 'infosystem.item')
        SQL
    );
    $nodeQuery->execute(['site_id' => $siteId]);
    if ((int) $nodeQuery->fetchColumn() !== 49) {
        throw new RuntimeException('Mechaniki dynamic Structure page bindings are incomplete.');
    }

    $detail = $db->prepare('SELECT page_type, page_config FROM nodes WHERE site_id = :site_id AND path = :path');
    $detail->execute(['site_id' => $siteId, 'path' => '/services/podveska']);
    $itemNode = $detail->fetch(PDO::FETCH_ASSOC);
    if (!is_array($itemNode) || $itemNode['page_type'] !== 'infosystem.item') {
        throw new RuntimeException('Suspension page is not an Infosystem item.');
    }
    $selection = json_decode((string) $itemNode['page_config'], true, flags: JSON_THROW_ON_ERROR);
    if (($selection['item_path'] ?? null) !== '/podveska'
        || ($selection['view'] ?? null) !== 'mechaniki.service.item') {
        throw new RuntimeException('Suspension item type configuration is invalid.');
    }

    $output = [];
    exec($infosystemsCommand . ' 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException('Repeat Infosystem activation failed: ' . implode("\n", $output));
    }
    $itemQuery->execute(['site_id' => $siteId]);
    if ((int) $itemQuery->fetchColumn() !== 103) {
        throw new RuntimeException('Repeat Infosystem activation duplicated items.');
    }

    // Prove the public page receives real item data and uses the registered
    // site-owned view rather than a leftover Documents HTML fragment.
    $core = new \Core\Extension\Core(new \Core\Extension\Api\RuntimeApi($db, $root));
    (new \Core\Extension\ModuleLoader($root))->load($core);
    $core->freeze();
    foreach ([
        '/services/podveska' => ['infosystem.item', 'Ремонт подвески'],
        '/problems/stuk' => ['infosystem.item', 'Стук на неровностях'],
        '/prices' => ['infosystem.list', 'Диагностика АКПП'],
    ] as $route => $expectation) {
        $nodeQuery = $db->prepare(
            'SELECT * FROM nodes WHERE site_id = :site_id AND path = :path'
        );
        $nodeQuery->execute(['site_id' => $siteId, 'path' => $route]);
        $node = $nodeQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($node) || (string) $node['page_type'] !== $expectation[0]) {
            throw new RuntimeException('Invalid dynamic page type at ' . $route);
        }
        $config = is_string($node['page_config'])
            ? json_decode($node['page_config'], true, flags: JSON_THROW_ON_ERROR)
            : $node['page_config'];
        $pageType = $core->pages()->definition((string) $node['page_type']);
        $context = new \Core\Page\PageExecutionContext(
            new \Core\Http\Request('GET', $route, $route, [], [], ['HTTP_HOST' => 'ru.rewired.ru'], []),
            new \Core\Site\SiteContext($siteId, 'mechaniki-stage-smoke', 'Mechaniki', 'ru.rewired.ru'),
            $node,
            $pageType->validateConfiguration($config),
            new \Core\View\Render\RenderContext(),
        );
        $html = $pageType->executor->execute($context)->value();
        if (!str_contains($html, $expectation[1])) {
            throw new RuntimeException('Infosystem page rendered no expected content at ' . $route);
        }
    }
} finally {
    $restore = $db->prepare('UPDATE installed_modules SET is_enabled = :enabled WHERE code = :code');
    $restore->execute([
        'enabled' => in_array($moduleEnabled, [true, 1, '1', 't', 'true'], true) ? 1 : 0,
        'code' => 'mechaniki',
    ]);
    if ($siteId !== null) {
        foreach (['nodes', 'documents', 'infosystems', 'site_domains', 'layouts'] as $table) {
            $delete = $db->prepare('DELETE FROM ' . $table . ' WHERE site_id = :site_id');
            $delete->execute(['site_id' => $siteId]);
        }
        $deleteSite = $db->prepare('DELETE FROM sites WHERE id = :id');
        $deleteSite->execute(['id' => $siteId]);
    }
}

fwrite(STDOUT, "MECHANIKI CMS STAGING OK\n");
