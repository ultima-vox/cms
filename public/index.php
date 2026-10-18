<?php

declare(strict_types=1);

use Core\Application;
use Core\ErrorHandler;
use Core\Http\Request;
use Core\Installer\InstallerApplication;
use Core\Installer\InstallerState;
use Dotenv\Dotenv;

// PHP's development server must serve real assets instead of routing CSS through the CMS.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (is_string($path) && str_starts_with($path, '/assets/')) {
        $asset = realpath(__DIR__ . $path);
        if ($asset !== false && str_starts_with($asset, __DIR__ . '/assets/') && is_file($asset)) {
            return false;
        }
    }
}

$rootPath = dirname(__DIR__);

require $rootPath . '/vendor/autoload.php';

Dotenv::createImmutable($rootPath)->safeLoad();
ErrorHandler::register();

$request = Request::fromGlobals();
if ((new InstallerState($rootPath))->installationRequired()) {
    (new InstallerApplication($rootPath))->run($request);
    exit;
}

(new Application($rootPath))->run($request);
