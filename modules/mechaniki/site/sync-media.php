#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Database;
use Core\Repository\SiteRepository;
use Dotenv\Dotenv;

$root = dirname(__DIR__, 3);
require_once $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();

if (array_diff(array_slice($argv, 1), ['--apply']) !== []) {
    fwrite(STDERR, "Usage: php modules/mechaniki/site/sync-media.php [--apply]\n");
    exit(2);
}
$apply = in_array('--apply', $argv, true);

$site = (new SiteRepository(Database::connection()))->findActiveByHost('ru.rewired.ru');
if ($site === null) {
    throw new RuntimeException('Only the active ru.rewired.ru staging installation can synchronize this media.');
}

$release = json_decode((string) file_get_contents($root . '/release.json'), true, flags: JSON_THROW_ON_ERROR);
$layout = $release['layout'] ?? null;
if (!in_array($layout, ['public', 'webroot'], true)) {
    throw new RuntimeException('Unrecognized CMS hosting layout.');
}

$manifest = json_decode(
    (string) file_get_contents(__DIR__ . '/media-manifest.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if (($manifest['schema_version'] ?? null) !== 1 || !is_array($manifest['assets'] ?? null)) {
    throw new RuntimeException('Invalid site media manifest.');
}

$pending = [];
$present = 0;
foreach ($manifest['assets'] as $asset) {
    $url = $asset['source_url'] ?? null;
    $target = $asset['target'] ?? null;
    $size = $asset['bytes'] ?? null;
    $hash = $asset['sha256'] ?? null;
    if (!is_string($url) || !is_string($target) || !is_int($size) || !is_string($hash)
        || $size < 1 || $size > 5_000_000
        || !preg_match('/^[a-f0-9]{64}$/D', $hash)
        || !preg_match('#^https://mechaniki\.ru/(?:assets/images/|upload/shop_5/)[a-zA-Z0-9_./-]+\.(?:jpe?g|png)$#D', $url)
        || str_contains($url, '/../')
        || !preg_match('#^/assets/mechaniki/legacy/[a-zA-Z0-9_-]+\.(?:jpe?g|png)$#D', $target)) {
        throw new RuntimeException('Site media manifest contains a forbidden URL or target.');
    }

    $targets = [$root . '/public' . $target];
    if ($layout === 'webroot') {
        $targets[] = dirname($root) . $target;
    }

    $validCopy = null;
    foreach ($targets as $file) {
        if (is_link($file)) {
            throw new RuntimeException('Symbolic links are not accepted as media targets.');
        }
        if (!is_file($file)) {
            continue;
        }
        if (!hash_equals($hash, (string) hash_file('sha256', $file))) {
            throw new RuntimeException('Existing media has a different checksum: ' . $file);
        }
        $validCopy = $file;
    }

    if (count(array_filter($targets, 'is_file')) === count($targets)) {
        $present++;
        continue;
    }

    $pending[] = [
        'url' => $url,
        'name' => basename($target),
        'targets' => $targets,
        'hash' => $hash,
        'bytes' => $size,
        'existing' => $validCopy,
    ];
}

fwrite(STDOUT, sprintf(
    "Mechaniki media: site #%d, manifest %d, present %d, pending %d, layout %s.\n",
    (int) $site['id'],
    count($manifest['assets']),
    $present,
    count($pending),
    $layout,
));
if (!$apply) {
    foreach ($pending as $item) {
        fwrite(STDOUT, "Pending: " . $item['name'] . " (" . $item['bytes'] . " bytes)\n");
    }
    fwrite(STDOUT, "Dry run only. Use --apply after checking webroot write permissions.\n");
    exit(0);
}

/** @return non-empty-string */
function verifiedDownload(string $url, int $expectedSize, string $hash): string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 25,
            'follow_location' => 0,
            'ignore_errors' => true,
            'header' => "Accept: image/jpeg,image/png\r\nUser-Agent: UltimaVoxCMS-Mechaniki-Media/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $bytes = @file_get_contents($url, false, $context, 0, $expectedSize + 1);
    /** @var list<string> $http_response_header */
    $headers = $http_response_header ?? [];
    if (!is_string($bytes)
        || !preg_match('#^HTTP/\d(?:\.\d)? 200(?:\s|$)#', (string) ($headers[0] ?? ''))
        || strlen($bytes) !== $expectedSize
        || !hash_equals($hash, hash('sha256', $bytes))) {
        throw new RuntimeException('Original image is unavailable or has changed: ' . $url);
    }
    $image = @getimagesizefromstring($bytes);
    if (!is_array($image) || !in_array($image['mime'] ?? null, ['image/jpeg', 'image/png'], true)) {
        throw new RuntimeException('Remote response is not a valid image: ' . $url);
    }

    return $bytes;
}

function publishMedia(string $file, string $content): void
{
    $directory = dirname($file);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create media directory: ' . $directory);
    }
    if (is_link($directory) || is_link($file) || (file_exists($file) && !is_file($file))) {
        throw new RuntimeException('Unsafe media destination: ' . $file);
    }
    $temporary = $directory . '/.mechaniki-' . bin2hex(random_bytes(8)) . '.tmp';
    if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to write the staging image: ' . $file);
    }
    @chmod($temporary, 0644);
    if (!rename($temporary, $file)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to publish staging image: ' . $file);
    }
}

foreach ($pending as $item) {
    $bytes = $item['existing'] !== null
        ? file_get_contents($item['existing'])
        : verifiedDownload($item['url'], $item['bytes'], $item['hash']);

    if (!is_string($bytes) || !hash_equals($item['hash'], hash('sha256', $bytes))) {
        throw new RuntimeException('Image checksum mismatch: ' . $item['name']);
    }
    foreach ($item['targets'] as $file) {
        if (!is_file($file)) {
            publishMedia($file, $bytes);
        }
    }
    fwrite(STDOUT, "Verified and saved: " . $item['name'] . "\n");
}

fwrite(STDOUT, "Original images are ready locally. Clear site page cache or wait for its TTL.\n");
