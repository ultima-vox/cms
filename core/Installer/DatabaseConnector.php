<?php

declare(strict_types=1);

namespace Core\Installer;

use PDO;
use PDOException;
use RuntimeException;

final readonly class DatabaseConnector
{
    public function connect(DatabaseConfig $config): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $config->host,
            $config->port,
            $config->database,
        );

        try {
            $pdo = new PDO($dsn, $config->user, $config->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->query('SELECT 1')->fetchColumn();

            return $pdo;
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Не удалось подключиться к PostgreSQL. Проверьте сервер, порт, базу, пользователя и пароль.',
                0,
                $exception,
            );
        }
    }
}
