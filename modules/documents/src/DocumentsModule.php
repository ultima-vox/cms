<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\Extension\Core;
use Core\Extension\ModuleInterface;

final readonly class DocumentsModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        unset($core);
    }
}
