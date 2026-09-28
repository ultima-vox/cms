<?php

declare(strict_types=1);

namespace Core\Security;

use Core\Repository\LoginAttemptRepository;
use Core\Repository\UserRepository;

final class AuthService
{
    private const SESSION_USER_ID = 'auth_user_id';

    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginAttemptRepository $attempts,
    ) {
    }

    public function login(string $email, string $password, string $ipAddress): LoginStatus
    {
        $email = trim($email);

        if ($email === '' || $password === '') {
            return LoginStatus::InvalidCredentials;
        }

        if ($this->attempts->isBlocked($email, $ipAddress)) {
            return LoginStatus::RateLimited;
        }

        $user = $this->users->findActiveByEmail($email);
        $hash = is_array($user) && isset($user['password_hash']) && is_string($user['password_hash'])
            ? $user['password_hash']
            : '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';

        if ($user === null || !password_verify($password, $hash)) {
            $this->attempts->recordFailure($email, $ipAddress);
            return LoginStatus::InvalidCredentials;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = (int) $user['id'];
        $this->attempts->clear($email, $ipAddress);
        $this->users->touchLastLogin((int) $user['id']);

        return LoginStatus::Success;
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_USER_ID]);
        session_regenerate_id(true);
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $userId = $_SESSION[self::SESSION_USER_ID] ?? null;

        if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
            return null;
        }

        return $this->users->findActiveById((int) $userId);
    }

    public function can(string $permission): bool
    {
        $user = $this->user();

        return $user !== null
            && $this->users->hasPermission((int) $user['id'], $permission);
    }
}
