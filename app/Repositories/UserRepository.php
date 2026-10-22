<?php

declare(strict_types=1);

namespace AlumniConnect\Repositories;

use AlumniConnect\Database\Connection;
use PDOException;
use RuntimeException;

final class UserRepository
{
    public function findForLogin(string $email): ?array
    {
        $statement = Connection::get()->prepare(
            'SELECT id, full_name, email, password_hash, status
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function createAccount(string $fullName, string $email, string $passwordHash, string $roleSlug): int
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $roleStatement = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
            $roleStatement->execute(['slug' => $roleSlug]);
            $roleId = $roleStatement->fetchColumn();

            if ($roleId === false) {
                throw new RuntimeException('The requested account role is not configured.');
            }

            $userStatement = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, status, created_at, updated_at)
                 VALUES (:full_name, :email, :password_hash, \'active\', UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $userStatement->execute([
                'full_name' => $fullName,
                'email' => $email,
                'password_hash' => $passwordHash,
            ]);
            $userId = (int) $pdo->lastInsertId();

            $roleAssignment = $pdo->prepare(
                'INSERT INTO user_roles (user_id, role_id, assigned_at)
                 VALUES (:user_id, :role_id, UTC_TIMESTAMP())'
            );
            $roleAssignment->execute([
                'user_id' => $userId,
                'role_id' => (int) $roleId,
            ]);

            $memberProfile = $pdo->prepare(
                'INSERT INTO member_profiles (user_id, profile_visibility, contact_sharing, created_at, updated_at)
                 VALUES (:user_id, \'members\', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $memberProfile->execute(['user_id' => $userId]);

            if ($roleSlug === 'alumni') {
                $profile = $pdo->prepare(
                    'INSERT INTO alumni_profiles (user_id, verification_status, profile_visibility, contact_sharing, created_at, updated_at)
                     VALUES (:user_id, \'pending\', \'members\', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
                );
                $profile->execute(['user_id' => $userId]);
            }

            $pdo->commit();
            return $userId;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DuplicateAccountException('An account with this email already exists.', 0, $exception);
            }

            throw $exception;
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function recordSuccessfulLogin(int $userId, ?string $replacementHash): void
    {
        $statement = Connection::get()->prepare(
            'UPDATE users
             SET last_login_at = UTC_TIMESTAMP(),
                 password_hash = COALESCE(:password_hash, password_hash),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :user_id'
        );
        $statement->execute([
            'password_hash' => $replacementHash,
            'user_id' => $userId,
        ]);
    }

    public function findAuthenticatedUser(int $userId): ?array
    {
        $statement = Connection::get()->prepare(
            'SELECT u.id, u.full_name, u.email, u.status, ap.verification_status
             FROM users u
             LEFT JOIN alumni_profiles ap ON ap.user_id = u.id
             WHERE u.id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $user = $statement->fetch();

        if (!is_array($user)) {
            return null;
        }

        $roleStatement = Connection::get()->prepare(
            'SELECT r.slug, r.label
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
             ORDER BY r.id'
        );
        $roleStatement->execute(['user_id' => $userId]);
        $user['roles'] = $roleStatement->fetchAll();

        return $user;
    }
}
