<?php

declare(strict_types=1);

namespace AlumniConnect\Database;

use PDO;
use PDOException;
use RuntimeException;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = require dirname(__DIR__, 2) . '/config/load.php';
        $database = $config['database'];

        if (
            !in_array('mysql', PDO::getAvailableDrivers(), true)
            || $database['username'] === ''
            || $database['password'] === ''
            || $database['password'] === 'CHANGE_ME'
            || !is_string($config['app_key'])
            || strlen($config['app_key']) < 64
            || $config['app_key'] === 'GENERATE_64_HEX_CHARACTERS'
        ) {
            throw new DatabaseUnavailable('Database configuration is incomplete. Run the local setup instructions.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $database['host'],
            $database['port'],
            $database['name'],
            $database['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $database['username'], $database['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            self::$pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $exception) {
            error_log('[database] Connection failed; check local database configuration and service status.');
            throw new DatabaseUnavailable('The database is unavailable. Check the local setup instructions.', 0, $exception);
        }

        return self::$pdo;
    }
}
