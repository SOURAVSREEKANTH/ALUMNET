<?php

declare(strict_types=1);

namespace AlumniConnect\Security;

use AlumniConnect\Repositories\UserRepository;

final class Auth
{
    private static bool $resolved = false;
    private static ?array $user = null;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;
        $userId = Session::userId();
        if ($userId === null) {
            return null;
        }

        $user = (new UserRepository())->findAuthenticatedUser($userId);

        if (!is_array($user) || $user['status'] !== 'active') {
            Session::logout();
            return null;
        }

        self::$user = $user;

        return self::$user;
    }

    public static function hasRole(array $user, string $role): bool
    {
        foreach ($user['roles'] as $assignedRole) {
            if ($assignedRole['slug'] === $role) {
                return true;
            }
        }

        return false;
    }
}
