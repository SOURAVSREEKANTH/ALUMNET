<?php

declare(strict_types=1);

namespace AlumniConnect\Security;

use RuntimeException;

final class Session
{
    private const IDLE_TIMEOUT_SECONDS = 1800;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::checkIdleTimeout();
            return;
        }

        if (headers_sent()) {
            throw new RuntimeException('The session could not start because response output has already begun.');
        }

        $config = require dirname(__DIR__, 2) . '/config/load.php';
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) self::IDLE_TIMEOUT_SECONDS);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $config['secure_cookies'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new RuntimeException('The session could not be started.');
        }

        self::checkIdleTimeout();
    }

    public static function authenticate(int $userId): void
    {
        self::start();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('The session could not be renewed after authentication.');
        }

        $_SESSION = [
            'user_id' => $userId,
            'last_activity' => time(),
            'csrf_token' => bin2hex(random_bytes(32)),
        ];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?: 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function flash(string $key, string $message): void
    {
        self::start();
        $_SESSION['flash'][$key] = $message;
    }

    public static function takeFlashes(): array
    {
        self::start();
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return is_array($flashes) ? $flashes : [];
    }

    public static function takeFlashesIfActive(): array
    {
        return session_status() === PHP_SESSION_ACTIVE ? self::takeFlashes() : [];
    }

    public static function userId(): ?int
    {
        self::start();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    private static function checkIdleTimeout(): void
    {
        $now = time();
        $lastActivity = $_SESSION['last_activity'] ?? null;

        if (is_int($lastActivity) && $now - $lastActivity > self::IDLE_TIMEOUT_SECONDS) {
            $_SESSION = [];
            session_regenerate_id(true);
        }

        $_SESSION['last_activity'] = $now;
    }
}
