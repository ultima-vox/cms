<?php

declare(strict_types=1);

use Core\Application;
use Core\ErrorHandler;
use Core\Http\Request;
use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);

require $rootPath . '/vendor/autoload.php';

Dotenv::createImmutable($rootPath)->safeLoad();
ErrorHandler::register();

(new Application($rootPath))->run(Request::fromGlobals());
