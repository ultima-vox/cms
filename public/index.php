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

// Coordinate code replacement across PHP workers sharing this installation.
$updateDirectory = $rootPath . '/storage/updates';
if (!is_dir($updateDirectory)) @mkdir($updateDirectory, 0755, true);
if (is_file($updateDirectory . '/maintenance.json')) {
    http_response_code(503);
    header('Retry-After: 30');
    exit('Обновление сайта не завершено. Требуется восстановление из резервной копии в storage/updates.');
}
if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) !== '/admin/updates/apply') {
    $requestLock = @fopen($updateDirectory . '/requests.lock', 'c');
    if ($requestLock === false || !flock($requestLock, LOCK_SH | LOCK_NB)) {
        http_response_code(503);
        header('Retry-After: 10');
        exit('Сайт обновляется. Повторите запрос через несколько секунд.');
    }
}

require $rootPath . '/vendor/autoload.php';

Dotenv::createImmutable($rootPath)->safeLoad();
ErrorHandler::register();

$request = Request::fromGlobals();
if ((new InstallerState($rootPath))->installationRequired()) {
    (new InstallerApplication($rootPath))->run($request);
    exit;
}

(new Application($rootPath))->run($request);
