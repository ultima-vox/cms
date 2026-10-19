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
            $config->driver . ':host=%s;port=%d;dbname=%s' . ($config->driver === 'mysql' ? ';charset=utf8mb4' : ''),
            $config->host,
            $config->port,
            $config->database,
        );

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => $config->driver === 'mysql',
                PDO::ATTR_TIMEOUT => 5,
            ];
            if ($config->driver === 'mysql') $options[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
            $pdo = new PDO($dsn, $config->user, $config->password, $options);
            $pdo->query('SELECT 1')->fetchColumn();
            if ($config->driver === 'mysql') $pdo->exec("SET time_zone = '+00:00'");

            if ($config->driver === 'mysql') {
                $logging = $pdo->query('SELECT @@GLOBAL.log_bin AS enabled, @@GLOBAL.log_bin_trust_function_creators AS trusted')->fetch(PDO::FETCH_ASSOC);
                if (!empty($logging['enabled']) && empty($logging['trusted'])) {
                    throw new RuntimeException('На сервере MySQL включён binary log, но создание триггеров обычным пользователем запрещено. Попросите провайдера разрешить log_bin_trust_function_creators либо выберите PostgreSQL. База ещё не изменена.');
                }
            }
            return $pdo;
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Не удалось подключиться к выбранной СУБД. Проверьте сервер, порт, базу, пользователя и пароль.',
                0,
                $exception,
            );
        }
    }
}
