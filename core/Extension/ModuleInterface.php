<?php

declare(strict_types=1);

namespace Core\Extension;

interface ModuleInterface
{
    public function register(Core $core): void;
}
