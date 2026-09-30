<?php

declare(strict_types=1);

use Core\Application;
use Core\ErrorHandler;
use Core\Http\Request;
use Core\Installer\InstallerApplication;
use Core\Installer\InstallerState;
use Dotenv\Dotenv;

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
