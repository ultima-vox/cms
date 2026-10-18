<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Updater;

use RuntimeException;
use ZipArchive;

/** Installation-wide state; no session dependency and no module-owned core tables. */
final readonly class UpdateState
{
    public function __construct(private string $root) {}

    public function offer(): ?array
    {
        $data = json_decode((string) @file_get_contents($this->root . '/storage/updates/offer.json'), true);
        return is_array($data) && is_array($data['release'] ?? null)
            && is_int($data['checked_at'] ?? null) && $data['checked_at'] > time() - 86400 ? $data : null;
    }

    public function save(array $release): void
    {
        $directory = $this->root . '/storage/updates';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Каталог обновлений недоступен для записи.');
        $temporary = $directory . '/offer-' . bin2hex(random_bytes(8));
        try {
            if (file_put_contents($temporary, json_encode(['checked_at' => time(), 'release' => $release], JSON_THROW_ON_ERROR), LOCK_EX) === false
                || !chmod($temporary, 0600) || !rename($temporary, $directory . '/offer.json')) throw new RuntimeException('Не удалось сохранить результат проверки.');
        } finally { if (is_file($temporary)) unlink($temporary); }
    }

    /** @return list<array<string, mixed>> */
    public function history(): array
    {
        $paths = glob($this->root . '/storage/updates/backup-*/journal.json') ?: [];
        rsort($paths, SORT_STRING);
        $result = [];
        foreach (array_slice($paths, 0, 20) as $path) {
            if (is_link(dirname($path)) || is_link($path) || filesize($path) > 4_000_000) continue;
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data)) {
                unset($data['files']);
                $result[] = $data + ['backup' => basename(dirname($path))];
            }
        }
        return $result;
    }

    /** @return list<array{label:string,ok:bool}> */
    public function readiness(): array
    {
        $directory = $this->root . '/storage/updates';
        $space = @disk_free_space($this->root);
        return [
            ['label' => 'PHP 8.4 или новее', 'ok' => PHP_VERSION_ID >= 80400],
            ['label' => 'Расширение PHP zip', 'ok' => class_exists(ZipArchive::class)],
            ['label' => 'HTTPS: OpenSSL и allow_url_fopen', 'ok' => extension_loaded('openssl') && (bool) ini_get('allow_url_fopen')],
            ['label' => 'Запись в каталоги CMS и обновлений', 'ok' => is_writable($this->root) && is_writable(is_dir($directory) ? $directory : $this->root . '/storage')],
            ['label' => 'Свободное место: не менее 320 МБ', 'ok' => $space !== false && $space >= 320_000_000],
            ['label' => 'Предыдущее обновление завершено', 'ok' => !is_file($directory . '/maintenance.json')],
        ];
    }
}
