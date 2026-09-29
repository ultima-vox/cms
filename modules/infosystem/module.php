<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

return [
    'code' => 'infosystem',
    'name' => 'Infosystems',
    'version' => '1.0.0',
    'requires' => [
        'core' => '>=0.1.0',
    ],
    'provider' => UltimaVox\Modules\Infosystem\InfosystemModule::class,
];
