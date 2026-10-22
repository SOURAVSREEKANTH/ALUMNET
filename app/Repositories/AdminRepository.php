<?php

declare(strict_types=1);

namespace AlumniConnect\Repositories;

use AlumniConnect\Database\Connection;
use InvalidArgumentException;
use PDO;
use Throwable;

final class AdminRepository
{
    public function operationalSummary(): array
    {
        $statement = Connection::get()->query(
            'SELECT
                (SELECT COUNT(*) FROM users WHERE status = \'active\') AS active_accounts,
                (SELECT COUNT(*) FROM alumni_profiles WHERE verification_status = \'pending\') AS pending_alumni,
                (SELECT COUNT(*) FROM events WHERE status = \'published\' AND starts_at > UTC_TIMESTAMP()) AS upcoming_events,
                (SELECT COUNT(*) FROM event_rsvps WHERE state = \'attending\') AS confirmed_rsvps,
                (SELECT COUNT(*) FROM event_checkins WHERE checked_in_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)) AS checkins_last_30_days,
                (SELECT COUNT(*) FROM opportunities WHERE status = \'open\'
                    AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())) AS open_opportunities,
                (SELECT COUNT(*) FROM event_photos WHERE moderation_status = \'pending\' AND deleted_at IS NULL) AS pending_photos'
        );
        $summary = $statement->fetch();
        if (!is_array($summary)) {
            throw new \RuntimeException('Operational metrics could not be loaded.');
        }

        foreach ($summary as $key => $value) {
            $summary[$key] = (int) $value;
        }

        return $summary;
    }

    public function pendingAlumni(): array
    {
        $statement = Connection::get()->query(
            'SELECT
                users.id,
                users.full_name,
                users.email,
                users.created_at,
                alumni_profiles.department_id,
                alumni_profiles.batch_id,
                alumni_profiles.company,
                alumni_profiles.job_title,
                alumni_profiles.verification_status
             FROM alumni_profiles
             INNER JOIN users ON users.id = alumni_profiles.user_id
             INNER JOIN user_roles ON user_roles.user_id = users.id
             INNER JOIN roles ON roles.id = user_roles.role_id AND roles.slug = \'alumni\'
             WHERE alumni_profiles.verification_status = \'pending\'
               AND users.status = \'active\'
             ORDER BY users.created_at, users.id
             LIMIT 50'
        );

        return $statement->fetchAll();
    }

    public function pendingPhotos(): array
    {
        $statement = Connection::get()->query(
            'SELECT
                photo.id,
                photo.caption,
                photo.created_at,
                album.event_id,
                event.title AS event_title,
                uploader.full_name AS uploader_name
             FROM event_photos photo
             INNER JOIN photo_albums album ON album.id = photo.album_id
             INNER JOIN events event ON event.id = album.event_id
             INNER JOIN users uploader ON uploader.id = photo.uploader_user_id
             WHERE photo.moderation_status = \'pending\'
               AND photo.deleted_at IS NULL
             ORDER BY photo.created_at, photo.id
             LIMIT 50'
        );

        return $statement->fetchAll();
    }

    public function reviewAlumnus(int $adminId, int $userId, string $decision): void
    {
        if (!in_array($decision, ['verified', 'rejected'], true)) {
            throw new InvalidArgumentException('Choose a valid alumni verification decision.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $profile = $pdo->prepare(
                'SELECT verification_status
                 FROM alumni_profiles
                 WHERE user_id = :user_id
                 FOR UPDATE'
            );
            $profile->execute(['user_id' => $userId]);
            if ($profile->fetchColumn() !== 'pending') {
                throw new InvalidArgumentException('That alumni account is not awaiting verification.');
            }

            $update = $pdo->prepare(
                'UPDATE alumni_profiles
                 SET verification_status = :decision, verified_by = :admin_id, verified_at = UTC_TIMESTAMP()
                 WHERE user_id = :user_id AND verification_status = \'pending\''
            );
            $update->execute([
                'decision' => $decision,
                'admin_id' => $adminId,
                'user_id' => $userId,
            ]);
            $audit = $pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, target_type, target_id, metadata)
                 VALUES (:admin_id, :action, \'alumni_profile\', :user_id, JSON_OBJECT(\'decision\', :decision))'
            );
            $audit->execute([
                'admin_id' => $adminId,
                'action' => 'alumni.' . $decision,
                'user_id' => $userId,
                'decision' => $decision,
            ]);
            if ($userId !== $adminId) {
                NotificationRepository::create(
                    $pdo,
                    $userId,
                    $adminId,
                    'alumni_' . $decision,
                    'alumni',
                    $userId
                );
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function reviewPhoto(int $adminId, int $photoId, string $decision): void
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Choose a valid photo moderation decision.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $photo = $pdo->prepare(
                'SELECT photo.uploader_user_id, album.event_id
                 FROM event_photos photo
                 INNER JOIN photo_albums album ON album.id = photo.album_id
                 WHERE photo.id = :photo_id
                   AND photo.moderation_status = \'pending\'
                   AND photo.deleted_at IS NULL
                 FOR UPDATE'
            );
            $photo->execute(['photo_id' => $photoId]);
            $pendingPhoto = $photo->fetch();
            if (!is_array($pendingPhoto)) {
                throw new InvalidArgumentException('That photo is no longer awaiting moderation.');
            }

            $update = $pdo->prepare(
                'UPDATE event_photos
                 SET moderation_status = :decision
                 WHERE id = :photo_id
                   AND moderation_status = \'pending\'
                   AND deleted_at IS NULL'
            );
            $update->execute([
                'decision' => $decision,
                'photo_id' => $photoId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('That photo is no longer awaiting moderation.');
            }

            $audit = $pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, target_type, target_id, metadata)
                 VALUES (:admin_id, :action, \'event_photo\', :photo_id, JSON_OBJECT(\'decision\', :decision))'
            );
            $audit->execute([
                'admin_id' => $adminId,
                'action' => 'memory_photo.' . $decision,
                'photo_id' => $photoId,
                'decision' => $decision,
            ]);
            if ((int) $pendingPhoto['uploader_user_id'] !== $adminId) {
                NotificationRepository::create(
                    $pdo,
                    (int) $pendingPhoto['uploader_user_id'],
                    $adminId,
                    'photo_' . $decision,
                    'event',
                    (int) $pendingPhoto['event_id']
                );
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function roles(): array
    {
        return Connection::get()->query(
            'SELECT slug, label FROM roles ORDER BY id'
        )->fetchAll();
    }

    public function setAccountStatus(int $adminId, int $userId, string $status): void
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new InvalidArgumentException('Choose a valid account status.');
        }
        if ($adminId === $userId && $status === 'suspended') {
            throw new InvalidArgumentException('You cannot suspend your own administrator account.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $activeAdministrators = $this->lockActiveAdministrators($pdo);
            $account = $pdo->prepare('SELECT status FROM users WHERE id = :user_id FOR UPDATE');
            $account->execute(['user_id' => $userId]);
            $currentStatus = $account->fetchColumn();
            if (!is_string($currentStatus)) {
                throw new InvalidArgumentException('That account could not be found.');
            }
            if ($currentStatus === $status) {
                throw new InvalidArgumentException('That account already has the selected status.');
            }
            if (
                $status === 'suspended'
                && $currentStatus === 'active'
                && in_array($userId, $activeAdministrators, true)
                && count($activeAdministrators) <= 1
            ) {
                throw new InvalidArgumentException('The last active administrator account cannot be suspended.');
            }

            $update = $pdo->prepare('UPDATE users SET status = :status WHERE id = :user_id');
            $update->execute(['status' => $status, 'user_id' => $userId]);
            $audit = $pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, target_type, target_id, metadata)
                 VALUES (:admin_id, :action, \'user\', :user_id, JSON_OBJECT(\'status\', :status))'
            );
            $audit->execute([
                'admin_id' => $adminId,
                'action' => $status === 'suspended' ? 'account.suspended' : 'account.activated',
                'user_id' => $userId,
                'status' => $status,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function changeAccountRole(int $adminId, int $userId, string $roleSlug, string $action): void
    {
        if (!in_array($action, ['grant', 'revoke'], true)) {
            throw new InvalidArgumentException('Choose whether to grant or remove a role.');
        }
        if ($adminId === $userId) {
            throw new InvalidArgumentException('You cannot change your own account roles.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $activeAdministrators = $this->lockActiveAdministrators($pdo);
            $account = $pdo->prepare('SELECT id FROM users WHERE id = :user_id FOR UPDATE');
            $account->execute(['user_id' => $userId]);
            if ($account->fetchColumn() === false) {
                throw new InvalidArgumentException('That account could not be found.');
            }

            $role = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug');
            $role->execute(['slug' => $roleSlug]);
            $roleId = $role->fetchColumn();
            if ($roleId === false) {
                throw new InvalidArgumentException('Choose a role from the available list.');
            }

            $assignment = $pdo->prepare(
                'SELECT role_id FROM user_roles
                 WHERE user_id = :user_id AND role_id = :role_id
                 FOR UPDATE'
            );
            $assignment->execute(['user_id' => $userId, 'role_id' => $roleId]);
            $alreadyAssigned = $assignment->fetchColumn() !== false;
            if (($action === 'grant' && $alreadyAssigned) || ($action === 'revoke' && !$alreadyAssigned)) {
                throw new InvalidArgumentException(
                    $action === 'grant' ? 'That account already has this role.' : 'That account does not have this role.'
                );
            }

            if ($action === 'revoke') {
                $roleCount = $pdo->prepare('SELECT COUNT(*) FROM user_roles WHERE user_id = :user_id');
                $roleCount->execute(['user_id' => $userId]);
                if ((int) $roleCount->fetchColumn() <= 1) {
                    throw new InvalidArgumentException('An account must retain at least one role.');
                }
                if (
                    $roleSlug === 'admin'
                    && in_array($userId, $activeAdministrators, true)
                    && count($activeAdministrators) <= 1
                ) {
                    throw new InvalidArgumentException('The last active administrator role cannot be removed.');
                }
                $change = $pdo->prepare(
                    'DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id'
                );
            } else {
                $change = $pdo->prepare(
                    'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
                );
            }
            if ($action === 'grant' && $roleSlug === 'alumni') {
                $alumniProfile = $pdo->prepare(
                    'SELECT user_id FROM alumni_profiles WHERE user_id = :user_id FOR UPDATE'
                );
                $alumniProfile->execute(['user_id' => $userId]);
                if ($alumniProfile->fetchColumn() === false) {
                    $createProfile = $pdo->prepare(
                        'INSERT INTO alumni_profiles (
                            user_id, verification_status, profile_visibility, contact_sharing, created_at, updated_at
                         ) VALUES (:user_id, \'pending\', \'members\', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
                    );
                    $createProfile->execute(['user_id' => $userId]);
                }
            }
            $change->execute(['user_id' => $userId, 'role_id' => $roleId]);

            $audit = $pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, target_type, target_id, metadata)
                 VALUES (:admin_id, :action, \'user_role\', :user_id, JSON_OBJECT(\'role\', :role_slug))'
            );
            $audit->execute([
                'admin_id' => $adminId,
                'action' => $action === 'grant' ? 'account.role_granted' : 'account.role_revoked',
                'user_id' => $userId,
                'role_slug' => $roleSlug,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function accounts(string $query, int $page): array
    {
        $where = '';
        $parameters = [];
        if ($query !== '') {
            $escapedQuery = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);
            $where = ' WHERE u.full_name LIKE :search_name ESCAPE \'!\'
                        OR u.email LIKE :search_email ESCAPE \'!\'';
            $needle = '%' . $escapedQuery . '%';
            $parameters = ['search_name' => $needle, 'search_email' => $needle];
        }

        $pdo = Connection::get();
        $count = $pdo->prepare('SELECT COUNT(*) FROM users u' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $perPage = 25;
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pageCount);

        $statement = $pdo->prepare(
            'SELECT
                u.id,
                u.full_name,
                u.email,
                u.status,
                u.created_at,
                u.last_login_at,
                mp.headline,
                mp.job_title,
                mp.company,
                mp.profile_visibility,
                d.name AS department_name,
                gb.graduation_year,
                ap.verification_status,
                (
                    SELECT GROUP_CONCAT(r.label ORDER BY r.id SEPARATOR \', \')
                    FROM user_roles ur
                    INNER JOIN roles r ON r.id = ur.role_id
                    WHERE ur.user_id = u.id
                ) AS role_labels,
                (
                    SELECT GROUP_CONCAT(r.slug ORDER BY r.id SEPARATOR \',\')
                    FROM user_roles ur
                    INNER JOIN roles r ON r.id = ur.role_id
                    WHERE ur.user_id = u.id
                ) AS role_slugs,
                CASE
                    WHEN mp.user_id IS NULL THEN \'Profile missing\'
                    WHEN mp.headline IS NOT NULL
                        OR mp.job_title IS NOT NULL
                        OR mp.company IS NOT NULL
                        OR mp.department_id IS NOT NULL
                        OR mp.biography IS NOT NULL THEN \'Profile started\'
                    ELSE \'Account only\'
                END AS profile_status
             FROM users u
             LEFT JOIN member_profiles mp ON mp.user_id = u.id
             LEFT JOIN departments d ON d.id = mp.department_id
             LEFT JOIN graduation_batches gb ON gb.id = mp.batch_id
             LEFT JOIN alumni_profiles ap ON ap.user_id = u.id'
            . $where . '
             ORDER BY u.created_at DESC, u.id DESC
             LIMIT :result_limit OFFSET :result_offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':result_limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':result_offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return [
            'accounts' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'page_count' => $pageCount,
        ];
    }

    private function lockActiveAdministrators(PDO $pdo): array
    {
        $statement = $pdo->query(
            'SELECT users.id
             FROM users
             INNER JOIN user_roles ON user_roles.user_id = users.id
             INNER JOIN roles ON roles.id = user_roles.role_id AND roles.slug = \'admin\'
             WHERE users.status = \'active\'
             ORDER BY users.id
             FOR UPDATE'
        );

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
