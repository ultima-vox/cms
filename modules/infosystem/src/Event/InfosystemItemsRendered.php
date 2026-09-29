<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem\Event;

final readonly class InfosystemItemsRendered
{
    public function __construct(
        public int $infosystemId,
        public int $itemCount,
        public string $viewCode,
    ) {
    }
}
