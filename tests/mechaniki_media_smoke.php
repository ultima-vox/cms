<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/modules/mechaniki/src/SiteMedia.php';

use UltimaVox\Modules\Mechaniki\SiteMedia;

$root = sys_get_temp_dir() . '/mechaniki-media-' . bin2hex(random_bytes(6));
$target = '/assets/mechaniki/legacy/group_650.jpeg';
$origin = 'https://mechaniki.ru/upload/shop_5/6/5/0/group_650/group_650.jpeg';
$document = '<img src="' . $origin . '"><a href="https://elsewhere.example/photo.jpg">Reference</a>';

@mkdir($root . '/modules/mechaniki/site', 0755, true);
@mkdir($root . '/public/assets/mechaniki/legacy', 0755, true);
file_put_contents($root . '/modules/mechaniki/site/media-manifest.json', json_encode([
    'schema_version' => 1,
    'assets' => [['source_url' => $origin, 'target' => $target]],
], JSON_THROW_ON_ERROR));

try {
    file_put_contents($root . '/release.json', '{"layout":"public"}');
    if (SiteMedia::localizeHtml($document, $root) !== $document) {
        throw new RuntimeException('Missing image was incorrectly localized.');
    }
    file_put_contents($root . '/public' . $target, 'fixture');
    $localized = str_replace($origin, $target, $document);
    if (SiteMedia::localizeHtml($document, $root) !== $localized) {
        throw new RuntimeException('Verified local image URL was not applied.');
    }

    file_put_contents($root . '/release.json', '{"layout":"webroot"}');
    if (SiteMedia::localizeHtml($document, $root) !== $document) {
        throw new RuntimeException('Webroot URL was exposed without public mirror.');
    }
    @mkdir(dirname($root) . '/assets/mechaniki/legacy', 0755, true);
    file_put_contents(dirname($root) . $target, 'fixture');
    if (SiteMedia::localizeHtml($document, $root) !== $localized) {
        throw new RuntimeException('Webroot mirror was not considered.');
    }
    if (!str_contains($localized, 'https://elsewhere.example/photo.jpg')) {
        throw new RuntimeException('Unrelated URLs were changed.');
    }
} finally {
    @unlink($root . '/release.json');
    @unlink($root . '/modules/mechaniki/site/media-manifest.json');
    @unlink($root . '/public' . $target);
    @unlink(dirname($root) . $target);
    @rmdir($root . '/modules/mechaniki/site');
    @rmdir($root . '/modules/mechaniki');
    @rmdir($root . '/modules');
    @rmdir($root . '/public/assets/mechaniki/legacy');
    @rmdir($root . '/public/assets/mechaniki');
    @rmdir($root . '/public/assets');
    @rmdir($root . '/public');
    @rmdir($root);
}
echo "MECHANIKI MEDIA URL REWRITE OK\n";
