<?php

declare(strict_types=1);

namespace Core\Http;

final readonly class UploadedFile
{
    public function __construct(
        public string $clientName,
        public string $temporaryPath,
        public int $size,
        public int $error,
    ) {
    }

    /** @param array<string, mixed> $value */
    public static function fromPhpFile(array $value): ?self
    {
        $name = $value['name'] ?? null;
        $temporaryPath = $value['tmp_name'] ?? null;
        $size = $value['size'] ?? null;
        $error = $value['error'] ?? null;

        if (!is_string($name)
            || !is_string($temporaryPath)
            || (!is_int($size) && !(is_string($size) && ctype_digit($size)))
            || (!is_int($error) && !(is_string($error) && ctype_digit($error)))) {
            return null;
        }

        return new self(
            clientName: $name,
            temporaryPath: $temporaryPath,
            size: (int) $size,
            error: (int) $error,
        );
    }

    public function successful(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }
}
