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
        $port = self::env('DB_PORT', '5432');
        $database = self::env('DB_NAME');
        $user = self::env('DB_USER');
        $password = self::env('DB_PASS', '');

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $host,
            $port,
            $database,
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$instance = new PDO($dsn, $user, $password, $options);
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
