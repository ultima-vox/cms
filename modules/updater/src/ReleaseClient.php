<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Updater;

use RuntimeException;

final class ReleaseClient
{
    public const FEED = 'https://github.com/ultima-vox/cms/releases/latest/download/update.json';

    /** @return array<string, mixed> */
    public function latest(): array
    {
        $manifest = json_decode($this->download(self::FEED, 1_000_000), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($manifest)
            || !preg_match('/^0\.1\.[0-9]+$/', $manifest['version'] ?? '')
            || !preg_match('/^[a-f0-9]{64}$/', $manifest['sha256'] ?? '')
            || !preg_match('~^https://github\.com/ultima-vox/cms/releases/download/v0\.1\.[0-9]+/cms-update\.zip$~D', $manifest['url'] ?? '')
            || !is_array($manifest['changes'] ?? null)
            || count($manifest['changes']) > 100) {
            throw new RuntimeException('Некорректный манифест обновления.');
        }
        foreach ($manifest['changes'] as $line) {
            if (!is_string($line) || strlen($line) > 4000) {
                throw new RuntimeException('Некорректный список изменений.');
            }
        }
        return $manifest;
    }

    public function download(string $url, int $maximum): string
    {
        for ($redirect = 0; $redirect < 6; $redirect++) {
            $parts = parse_url($url);
            if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
                || !in_array($parts['host'] ?? '', ['github.com', 'release-assets.githubusercontent.com', 'objects.githubusercontent.com'], true)
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                throw new RuntimeException('Недопустимый адрес сервера обновлений.');
            }
            $context = stream_context_create(['http' => [
                'timeout' => 30, 'follow_location' => 0, 'ignore_errors' => true,
                'header' => "User-Agent: Vox-CMS-Updater\r\nAccept: application/octet-stream\r\n",
            ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $stream = @fopen($url, 'rb', false, $context);
            if ($stream === false) {
                throw new RuntimeException('Сервер обновлений недоступен. Проверьте исходящий HTTPS и allow_url_fopen.');
            }
            try {
                $headers = stream_get_meta_data($stream)['wrapper_data'] ?? [];
                $status = $headers[0] ?? '';
                if (preg_match('~^HTTP/\S+ 30[12378]\b~', $status)) {
                    $location = null;
                    foreach ($headers as $header) {
                        if (stripos($header, 'Location:') === 0) {
                            $location = trim(substr($header, 9));
                        }
                    }
                    if ($location === null) {
                        throw new RuntimeException('Некорректное перенаправление обновления.');
                    }
                    $url = $location;
                    continue;
                }
                if (!preg_match('~^HTTP/\S+ 200\b~', $status)) {
                    throw new RuntimeException('Релиз недоступен: ' . $status);
                }
                $bytes = stream_get_contents($stream, $maximum + 1);
                if ($bytes === false || strlen($bytes) > $maximum || !feof($stream)) {
                    throw new RuntimeException('Превышен размер загрузки или загрузка не завершена.');
                }
                return $bytes;
            } finally {
                fclose($stream);
            }
        }
        throw new RuntimeException('Слишком много перенаправлений обновления.');
    }
}
