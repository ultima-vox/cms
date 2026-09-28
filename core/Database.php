 PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Всегда возвращаем ассоциативный массив
                PDO::ATTR_EMULATE_PREPARES   => false, // Защита от SQL-инъекций на уровне драйвера
            ];

            try {
                self::\(instance = new PDO(\)dsn, \(user,\)pass, $options);
            } catch (PDOException $e) {
                // Прячем реальную ошибку БД в продакшене, оставляя ее в логах
                throw new RuntimeException('Ошибка подключения к базе данных. Проверьте .env файл.');
            }
        }

        return self::$instance;
    }
}
