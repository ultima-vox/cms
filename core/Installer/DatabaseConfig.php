<?php

declare(strict_types=1);

namespace Core\Installer;

use RuntimeException;

final readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $user,
        public string $password,
    ) {
        if (!$this->validHost($host)) {
            throw new RuntimeException('Некорректный адрес PostgreSQL-сервера.');
        }
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Порт PostgreSQL должен быть в диапазоне 1–65535.');
        }
        if (!$this->validIdentifier($database)) {
            throw new RuntimeException('Некорректное имя базы данных.');
        }
        if (!$this->validIdentifier($user)) {
            throw new RuntimeException('Некорректное имя пользователя базы данных.');
        }
        if (strlen($password) > 1024) {
            throw new RuntimeException('Пароль базы данных имеет недопустимую длину.');
        }
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $port = filter_var($input['port'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($port)) {
            throw new RuntimeException('Некорректный порт PostgreSQL.');
        }

        return new self(
            host: trim((string) ($input['host'] ?? '')),
            port: $port,
            database: trim((string) ($input['database'] ?? '')),
            user: trim((string) ($input['user'] ?? '')),
            password: (string) ($input['password'] ?? ''),
        );
    }

    /** @return array{host:string,port:int,database:string,user:string,password:string} */
    public function toSession(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'user' => $this->user,
            'password' => $this->password,
        ];
    }

    /** @param array<string, mixed> $value */
    public static function fromSession(array $value): self
    {
        return new self(
            host: (string) ($value['host'] ?? ''),
            port: (int) ($value['port'] ?? 0),
            database: (string) ($value['database'] ?? ''),
            user: (string) ($value['user'] ?? ''),
            password: (string) ($value['password'] ?? ''),
        );
    }

    private function validHost(string $host): bool
    {
        return $host !== ''
            && strlen($host) <= 253
            && !preg_match('/[\x00-\x20;]/', $host);
    }

    private function validIdentifier(string $value): bool
    {
        return $value !== ''
            && strlen($value) <= 120
            && preg_match('/^[A-Za-z0-9_.-]+$/', $value) === 1;
    }
}
