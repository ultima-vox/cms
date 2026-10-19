<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = self::env('DB_HOST');
        $driver = self::env('DB_DRIVER', 'pgsql');
        if (!in_array($driver, ['pgsql', 'mysql'], true)) throw new RuntimeException('DB_DRIVER: pgsql или mysql (legacy).');
        $port = self::env('DB_PORT', $driver === 'mysql' ? '3306' : '5432');
        $database = self::env('DB_NAME');
        $user = self::env('DB_USER');
        $password = self::env('DB_PASS', '');

        $dsn = sprintf(
            $driver . ':host=%s;port=%s;dbname=%s' . ($driver === 'mysql' ? ';charset=utf8mb4' : ''),
            $host,
            $port,
            $database,
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => $driver === 'mysql',
        ];

        try {
            if ($driver === 'mysql') $options[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
            self::$instance = new PDO($dsn, $user, $password, $options);
            if ($driver === 'mysql') self::$instance->exec("SET time_zone = '+00:00'");
        } catch (PDOException $exception) {
            error_log($exception->getMessage());

            throw new RuntimeException(
                'Ошибка подключения к базе данных. Проверьте параметры DB_* в .env.'
            );
        }

        return self::$instance;
    }

    private static function env(string $name, ?string $default = null): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        if ($value === false || $value === null || $value === '') {
            if ($default !== null) {
                return $default;
            }

            throw new RuntimeException(sprintf(
                'Не задана обязательная переменная окружения %s.',
                $name,
            ));
        }

        return (string) $value;
    }
}
