<?php

declare(strict_types=1);

namespace AlumniConnect\Services;

use AlumniConnect\Repositories\UserRepository;
use AlumniConnect\Security\LoginRateLimiter;
use AlumniConnect\Security\Session;
use RuntimeException;

final class AuthenticationService
{
    private static ?string $dummyPasswordHash = null;

    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginRateLimiter $rateLimiter
    ) {
    }

    public function login(string $email, string $password, string $remoteAddress): LoginResult
    {
        $normalizedEmail = self::normalizeEmail($email);

        if ($this->rateLimiter->isBlocked('login', $normalizedEmail, $remoteAddress)) {
            return LoginResult::Throttled;
        }

        $user = $this->users->findForLogin($normalizedEmail);
        $passwordHash = $user['password_hash'] ?? self::dummyPasswordHash();
        $passwordMatches = password_verify($password, $passwordHash);
        if (
            $user === null
            || !$passwordMatches
            || $user['status'] !== 'active'
        ) {
            $this->rateLimiter->record('login', $normalizedEmail, $remoteAddress);
            return LoginResult::InvalidCredentials;
        }

        $replacementHash = password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)
            ? password_hash($password, PASSWORD_DEFAULT)
            : null;

        if ($replacementHash === false) {
            throw new RuntimeException('The password could not be rehashed.');
        }

        $this->users->recordSuccessfulLogin((int) $user['id'], $replacementHash);
        $this->rateLimiter->clearLoginAttempts($normalizedEmail, $remoteAddress);
        Session::authenticate((int) $user['id']);

        return LoginResult::Authenticated;
    }

    public function register(string $fullName, string $email, string $password, string $roleSlug): int
    {
        if (!in_array($roleSlug, ['alumni', 'student'], true)) {
            throw new RuntimeException('Self-registration is limited to alumni and student accounts.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('The password could not be secured.');
        }

        return $this->users->createAccount(
            trim($fullName),
            self::normalizeEmail($email),
            $passwordHash,
            $roleSlug
        );
    }

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private static function dummyPasswordHash(): string
    {
        if (self::$dummyPasswordHash === null) {
            $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            if ($hash === false) {
                throw new RuntimeException('The login verifier could not be initialized.');
            }

            self::$dummyPasswordHash = $hash;
        }

        return self::$dummyPasswordHash;
    }
}
