<?php

declare(strict_types=1);

use Core\Installer\DatabaseConfig;
use Core\Installer\DatabaseConnector;

require_once dirname(__DIR__) . '/vendor/autoload.php';

try {
    DatabaseConfig::fromInput([
        'host' => 'bad;host',
        'port' => '5432',
        'database' => 'cms_db',
        'user' => 'cms',
        'password' => '',
    ]);
    throw new RuntimeException('Invalid database host was accepted.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Invalid database host was accepted.') {
        throw $exception;
    }
}

$config = new DatabaseConfig(
    host: (string) getenv('DB_HOST'),
    port: (int) getenv('DB_PORT'),
    database: (string) getenv('DB_NAME'),
    user: (string) getenv('DB_USER'),
    password: (string) getenv('DB_PASS'),
);

$pdo = (new DatabaseConnector())->connect($config);
if ((int) $pdo->query('SELECT 1')->fetchColumn() !== 1) {
    throw new RuntimeException('Installer database connector failed health query.');
}

$session = $config->toSession();
$restored = DatabaseConfig::fromSession($session);
if ($restored->host !== $config->host
    || $restored->port !== $config->port
    || $restored->database !== $config->database
    || $restored->user !== $config->user
    || $restored->password !== $config->password) {
    throw new RuntimeException('Installer database config session round-trip failed.');
}

fwrite(STDOUT, "INSTALLER DATABASE OK\n");
