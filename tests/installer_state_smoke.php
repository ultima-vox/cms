<?php

declare(strict_types=1);

use Core\Installer\InstallerState;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$root = sys_get_temp_dir() . '/uvcms-installer-state-' . bin2hex(random_bytes(6));
if (!mkdir($root . '/storage', 0775, true) && !is_dir($root . '/storage')) {
    throw new RuntimeException('Unable to create installer-state smoke root.');
}

try {
    $state = new InstallerState($root);
    if (!$state->installationRequired()) {
        throw new RuntimeException('Fresh installation was not detected.');
    }

    file_put_contents($root . '/.env', "APP_ENV=production\n");
    if ($state->installationRequired()) {
        throw new RuntimeException('Legacy existing .env deployment was incorrectly forced into installer.');
    }
    unlink($root . '/.env');

    file_put_contents($root . '/storage/install.lock', "installed\n");
    if ($state->installationRequired()) {
        throw new RuntimeException('Installation lock was ignored.');
    }
} finally {
    @unlink($root . '/.env');
    @unlink($root . '/storage/install.lock');
    @rmdir($root . '/storage');
    @rmdir($root);
}

fwrite(STDOUT, "INSTALLER STATE OK\n");
