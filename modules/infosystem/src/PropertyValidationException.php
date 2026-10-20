<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use RuntimeException;

final class PropertyValidationException extends RuntimeException
{
    public function __construct(public readonly string $fieldCode, string $message)
    {
        parent::__construct($message);
    }
}
