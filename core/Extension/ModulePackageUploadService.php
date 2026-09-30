<?php

declare(strict_types=1);

namespace Core\Extension;

use Core\Http\UploadedFile;
use RuntimeException;

final readonly class ModulePackageUploadService
{
    private const MAX_PACKAGE_BYTES = 134_217_728; // 128 MiB
    private const MAX_SIGNATURE_BYTES = 16_384; // 16 KiB

    public function __construct(
        private ModulePackageLifecycle $lifecycle,
        private string $rootPath,
    ) {
    }

    public function install(UploadedFile $package, ?UploadedFile $signature = null): ModuleManifest
    {
        $this->assertPackage($package);
        if ($signature !== null) {
            $this->assertSignature($signature);
        }

        $directory = $this->rootPath . '/storage/tmp/module-packages';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Не удалось создать закрытый каталог временной загрузки модулей.');
        }

        $base = $directory . '/upload-' . bin2hex(random_bytes(16));
        $packagePath = $base . '.zip';
        $signaturePath = $packagePath . '.sig';

        try {
            if (!move_uploaded_file($package->temporaryPath, $packagePath)) {
                throw new RuntimeException('Не удалось принять загруженный ZIP-пакет.');
            }
            @chmod($packagePath, 0600);

            if ($signature !== null) {
                if (!move_uploaded_file($signature->temporaryPath, $signaturePath)) {
                    throw new RuntimeException('Не удалось принять файл подписи пакета.');
                }
                @chmod($signaturePath, 0600);
            }

            return $this->lifecycle->install($packagePath);
        } finally {
            if (is_file($signaturePath)) {
                @unlink($signaturePath);
            }
            if (is_file($packagePath)) {
                @unlink($packagePath);
            }
        }
    }

    private function assertPackage(UploadedFile $file): void
    {
        if (!$file->successful()) {
            throw new RuntimeException($this->uploadError('ZIP-пакет', $file->error));
        }
        if ($file->size < 1 || $file->size > self::MAX_PACKAGE_BYTES) {
            throw new RuntimeException('ZIP-пакет должен быть больше 0 и не превышать 128 MiB.');
        }
        if (!str_ends_with(strtolower($file->clientName), '.zip')) {
            throw new RuntimeException('Пакет модуля должен иметь расширение .zip.');
        }
        if (!is_uploaded_file($file->temporaryPath)) {
            throw new RuntimeException('Источник ZIP-пакета не является подтверждённой HTTP-загрузкой.');
        }
    }

    private function assertSignature(UploadedFile $file): void
    {
        if (!$file->successful()) {
            throw new RuntimeException($this->uploadError('Подпись пакета', $file->error));
        }
        if ($file->size < 1 || $file->size > self::MAX_SIGNATURE_BYTES) {
            throw new RuntimeException('Файл подписи должен быть больше 0 и не превышать 16 KiB.');
        }
        if (!str_ends_with(strtolower($file->clientName), '.sig')) {
            throw new RuntimeException('Файл подписи должен иметь расширение .sig.');
        }
        if (!is_uploaded_file($file->temporaryPath)) {
            throw new RuntimeException('Источник подписи не является подтверждённой HTTP-загрузкой.');
        }
    }

    private function uploadError(string $label, int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $label . ' превышает допустимый размер загрузки PHP.',
            UPLOAD_ERR_PARTIAL => $label . ' загружен не полностью.',
            UPLOAD_ERR_NO_FILE => $label . ' не выбран.',
            UPLOAD_ERR_NO_TMP_DIR => 'На сервере отсутствует временный каталог PHP uploads.',
            UPLOAD_ERR_CANT_WRITE => 'PHP не смог записать загрузку на диск.',
            UPLOAD_ERR_EXTENSION => 'Загрузка остановлена PHP-расширением.',
            default => $label . ': неизвестная ошибка загрузки (' . $error . ').',
        };
    }
}
