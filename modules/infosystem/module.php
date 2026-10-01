<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

return [
    'code' => 'infosystem',
    'name' => 'Infosystems',
    'version' => '1.0.0',
    'extension_api' => '^1.0',
    'default_enabled' => true,
    'requires' => [
        'core' => '>=0.1.0',
    ],
    'provider' => UltimaVox\Modules\Infosystem\InfosystemProvider::class,
];
