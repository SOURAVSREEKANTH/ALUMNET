<?php

declare(strict_types=1);

namespace AlumniConnect\Security;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function isValid(mixed $submittedToken): bool
    {
        if (!is_string($submittedToken) || $submittedToken === '') {
            return false;
        }

        Session::start();
        $knownToken = $_SESSION['csrf_token'] ?? '';

        return is_string($knownToken) && $knownToken !== '' && hash_equals($knownToken, $submittedToken);
    }
}
