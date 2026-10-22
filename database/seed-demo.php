<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'AlumniConnect\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$accounts = [
    ['email' => 'alumni.demo@example.test', 'name' => 'DEMO — Alumni Member', 'role' => 'alumni'],
    ['email' => 'student.demo@example.test', 'name' => 'DEMO — Student Member', 'role' => 'student'],
    ['email' => 'organizer.demo@example.test', 'name' => 'DEMO — Event Organizer', 'role' => 'organizer'],
    ['email' => 'admin.demo@example.test', 'name' => 'DEMO — Administrator', 'role' => 'admin'],
];

$resetPasswords = in_array('--reset-passwords', $argv, true);
$pdo = AlumniConnect\Database\Connection::get();
$pdo->beginTransaction();
$createdCredentials = [];

try {
    foreach ($accounts as $account) {
        $roleStatement = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $roleStatement->execute(['slug' => $account['role']]);
        $roleId = $roleStatement->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException('A required demo role is missing from the roles table.');
        }

        $findUser = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $findUser->execute(['email' => $account['email']]);
        $userId = $findUser->fetchColumn();
        $isNew = $userId === false;

        if ($isNew || $resetPasswords) {
            $password = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                throw new RuntimeException('A demo password could not be secured.');
            }

            if ($isNew) {
                $insertUser = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password_hash, status, created_at, updated_at)
                     VALUES (:full_name, :email, :password_hash, \'active\', UTC_TIMESTAMP(), UTC_TIMESTAMP())'
                );
                $insertUser->execute([
                    'full_name' => $account['name'],
                    'email' => $account['email'],
                    'password_hash' => $passwordHash,
                ]);
                $userId = (int) $pdo->lastInsertId();
            } else {
                $resetUser = $pdo->prepare(
                    'UPDATE users
                     SET password_hash = :password_hash, updated_at = UTC_TIMESTAMP()
                     WHERE id = :user_id'
                );
                $resetUser->execute(['password_hash' => $passwordHash, 'user_id' => (int) $userId]);
            }

            $createdCredentials[] = [$account['email'], $password];
        }

        $assignRole = $pdo->prepare(
            'INSERT INTO user_roles (user_id, role_id, assigned_at)
             VALUES (:user_id, :role_id, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE assigned_at = user_roles.assigned_at'
        );
        $assignRole->execute(['user_id' => (int) $userId, 'role_id' => (int) $roleId]);

        $memberProfile = $pdo->prepare(
            'INSERT INTO member_profiles (user_id, profile_visibility, contact_sharing, created_at, updated_at)
             VALUES (:user_id, \'members\', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE user_id = member_profiles.user_id'
        );
        $memberProfile->execute(['user_id' => (int) $userId]);

        if ($account['role'] === 'alumni') {
            $profile = $pdo->prepare(
                'INSERT INTO alumni_profiles (user_id, verification_status, profile_visibility, contact_sharing, created_at, updated_at)
                 VALUES (:user_id, \'pending\', \'members\', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE user_id = alumni_profiles.user_id'
            );
            $profile->execute(['user_id' => (int) $userId]);
        }
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

if ($createdCredentials === []) {
    echo "Demo accounts already exist; their passwords and account status were left unchanged. Use --reset-passwords to rotate them.\n";
    exit;
}

echo "Save these locally generated demo credentials now. Passwords are not stored in source or shown again unless explicitly reset.\n";
foreach ($createdCredentials as [$email, $password]) {
    echo $email . "  " . $password . PHP_EOL;
}
