<?php

declare(strict_types=1);

namespace AlumniConnect\Security;

use AlumniConnect\Database\Connection;
use RuntimeException;

final class LoginRateLimiter
{
    private const WINDOW_MINUTES = 15;
    private const MAX_ATTEMPTS = 8;

    public function isBlocked(string $action, ?string $email, string $remoteAddress): bool
    {
        [$identifierHash, $ipHash] = $this->hashKeys($email, $remoteAddress);
        $statement = Connection::get()->prepare(
            'SELECT COUNT(*)
             FROM auth_attempts
             WHERE action = :action
               AND created_at >= UTC_TIMESTAMP() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE
               AND (ip_hash = :ip_hash OR (:identifier_present IS NOT NULL AND identifier_hash = :identifier_hash))'
        );
        $statement->execute([
            'action' => $action,
            'ip_hash' => $ipHash,
            'identifier_present' => $identifierHash,
            'identifier_hash' => $identifierHash,
        ]);

        return (int) $statement->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public function record(string $action, ?string $email, string $remoteAddress): void
    {
        [$identifierHash, $ipHash] = $this->hashKeys($email, $remoteAddress);
        Connection::get()->exec('DELETE FROM auth_attempts WHERE created_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
        $statement = Connection::get()->prepare(
            'INSERT INTO auth_attempts (action, identifier_hash, ip_hash, created_at)
             VALUES (:action, :identifier_hash, :ip_hash, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'action' => $action,
            'identifier_hash' => $identifierHash,
            'ip_hash' => $ipHash,
        ]);
    }

    public function clearLoginAttempts(string $email, string $remoteAddress): void
    {
        [$identifierHash, $ipHash] = $this->hashKeys($email, $remoteAddress);
        $statement = Connection::get()->prepare(
            "DELETE FROM auth_attempts
             WHERE action = 'login' AND (identifier_hash = :identifier_hash OR ip_hash = :ip_hash)"
        );
        $statement->execute([
            'identifier_hash' => $identifierHash,
            'ip_hash' => $ipHash,
        ]);
    }

    private function hashKeys(?string $email, string $remoteAddress): array
    {
        $config = require dirname(__DIR__, 2) . '/config/load.php';
        $appKey = $config['app_key'];

        if (!is_string($appKey) || strlen($appKey) < 64 || $appKey === 'GENERATE_64_HEX_CHARACTERS') {
            throw new RuntimeException('A random local application key is required for authentication throttling.');
        }

        return [
            $email === null ? null : hash_hmac('sha256', strtolower($email), $appKey),
            hash_hmac('sha256', $remoteAddress, $appKey),
        ];
    }
}
