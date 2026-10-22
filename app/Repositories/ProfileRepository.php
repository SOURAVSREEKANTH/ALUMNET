<?php

declare(strict_types=1);

namespace AlumniConnect\Repositories;

use AlumniConnect\Database\Connection;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class ProfileRepository
{
    public function findForUser(int $userId): array
    {
        $statement = Connection::get()->prepare(
            'SELECT
                u.full_name,
                u.email,
                mp.department_id,
                mp.batch_id,
                mp.headline,
                mp.company,
                mp.job_title,
                mp.biography,
                mp.profile_visibility,
                mp.contact_sharing,
                d.name AS department_name,
                gb.graduation_year,
                ap.verification_status
             FROM users u
             INNER JOIN member_profiles mp ON mp.user_id = u.id
             LEFT JOIN departments d ON d.id = mp.department_id
             LEFT JOIN graduation_batches gb ON gb.id = mp.batch_id
             LEFT JOIN alumni_profiles ap ON ap.user_id = u.id
             WHERE u.id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $profile = $statement->fetch();

        if (!is_array($profile)) {
            throw new RuntimeException('The member profile could not be loaded. Run the Phase 4 database migration.');
        }

        return $profile;
    }

    public function departments(): array
    {
        return Connection::get()->query(
            'SELECT id, name FROM departments ORDER BY name'
        )->fetchAll();
    }

    public function batches(): array
    {
        return Connection::get()->query(
            'SELECT gb.id, gb.department_id, gb.graduation_year, d.name AS department_name
             FROM graduation_batches gb
             INNER JOIN departments d ON d.id = gb.department_id
             ORDER BY d.name, gb.graduation_year DESC'
        )->fetchAll();
    }

    public function save(int $userId, array $profile): void
    {
        $pdo = Connection::get();
        $departmentId = $profile['department_id'];
        $batchId = $profile['batch_id'];

        if ($departmentId !== null) {
            $department = $pdo->prepare('SELECT 1 FROM departments WHERE id = :department_id');
            $department->execute(['department_id' => $departmentId]);
            if ($department->fetchColumn() === false) {
                throw new InvalidArgumentException('Choose a department from the available list.');
            }
        }

        if ($batchId !== null) {
            if ($departmentId === null) {
                throw new InvalidArgumentException('Choose a department before selecting a graduating year.');
            }

            $batch = $pdo->prepare(
                'SELECT 1
                 FROM graduation_batches
                 WHERE id = :batch_id AND department_id = :department_id'
            );
            $batch->execute([
                'batch_id' => $batchId,
                'department_id' => $departmentId,
            ]);
            if ($batch->fetchColumn() === false) {
                throw new InvalidArgumentException('Choose a graduating year associated with the selected department.');
            }
        }

        $pdo->beginTransaction();
        try {
            $userUpdate = $pdo->prepare(
                'UPDATE users
                 SET full_name = :full_name, updated_at = UTC_TIMESTAMP()
                 WHERE id = :user_id AND status = \'active\''
            );
            $userUpdate->execute([
                'full_name' => $profile['full_name'],
                'user_id' => $userId,
            ]);
            if ($userUpdate->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT 1 FROM users WHERE id = :user_id AND status = \'active\'');
                $exists->execute(['user_id' => $userId]);
                if ($exists->fetchColumn() === false) {
                    throw new RuntimeException('The active member account could not be updated.');
                }
            }

            $profileUpdate = $pdo->prepare(
                'UPDATE member_profiles
                 SET department_id = :department_id,
                     batch_id = :batch_id,
                     headline = :headline,
                     company = :company,
                     job_title = :job_title,
                     biography = :biography,
                     profile_visibility = :profile_visibility,
                     contact_sharing = :contact_sharing,
                     updated_at = UTC_TIMESTAMP()
                 WHERE user_id = :user_id'
            );
            $profileUpdate->execute([
                'department_id' => $departmentId,
                'batch_id' => $batchId,
                'headline' => $profile['headline'],
                'company' => $profile['company'],
                'job_title' => $profile['job_title'],
                'biography' => $profile['biography'],
                'profile_visibility' => $profile['profile_visibility'],
                'contact_sharing' => $profile['contact_sharing'],
                'user_id' => $userId,
            ]);
            if ($profileUpdate->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT 1 FROM member_profiles WHERE user_id = :user_id');
                $exists->execute(['user_id' => $userId]);
                if ($exists->fetchColumn() === false) {
                    throw new RuntimeException('The member profile could not be updated. Run the Phase 4 database migration.');
                }
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function search(
        int $currentUserId,
        ?int $departmentId,
        ?int $graduationYear,
        string $query,
        int $page,
        ?int $suggestedDepartmentId,
        ?int $suggestedBatchId
    ): array {
        $pdo = Connection::get();
        $where = [
            'u.status = \'active\'',
            'u.id <> :current_user_id',
            'mp.profile_visibility IN (\'members\', \'public\')',
            'EXISTS (
                SELECT 1
                FROM user_roles ur
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = u.id AND r.slug IN (\'alumni\', \'student\')
            )',
        ];
        $parameters = ['current_user_id' => $currentUserId];

        if ($departmentId !== null) {
            $where[] = 'mp.department_id = :filter_department_id';
            $parameters['filter_department_id'] = $departmentId;
        }
        if ($graduationYear !== null) {
            $where[] = 'gb.graduation_year = :filter_graduation_year';
            $parameters['filter_graduation_year'] = $graduationYear;
        }
        if ($query !== '') {
            $escapedQuery = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);
            $needle = '%' . $escapedQuery . '%';
            $where[] = '(u.full_name LIKE :search_name ESCAPE \'!\'
                OR mp.headline LIKE :search_headline ESCAPE \'!\'
                OR mp.company LIKE :search_company ESCAPE \'!\'
                OR mp.job_title LIKE :search_job_title ESCAPE \'!\'
                OR mp.biography LIKE :search_biography ESCAPE \'!\'
                OR d.name LIKE :search_department ESCAPE \'!\')';
            $parameters += [
                'search_name' => $needle,
                'search_headline' => $needle,
                'search_company' => $needle,
                'search_job_title' => $needle,
                'search_biography' => $needle,
                'search_department' => $needle,
            ];
        }

        $from = ' FROM users u
                  INNER JOIN member_profiles mp ON mp.user_id = u.id
                  LEFT JOIN departments d ON d.id = mp.department_id
                  LEFT JOIN graduation_batches gb ON gb.id = mp.batch_id
                  LEFT JOIN alumni_profiles ap ON ap.user_id = u.id';
        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $count = $pdo->prepare('SELECT COUNT(*)' . $from . $whereSql);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $perPage = 20;
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pageCount);

        $statement = $pdo->prepare(
            'SELECT
                u.id,
                u.full_name,
                CASE WHEN mp.contact_sharing = 1 THEN u.email ELSE NULL END AS contact_email,
                mp.department_id,
                mp.batch_id,
                mp.headline,
                mp.company,
                mp.job_title,
                mp.biography,
                d.name AS department_name,
                gb.graduation_year,
                ap.verification_status,
                (
                    SELECT GROUP_CONCAT(DISTINCT directory_role.label ORDER BY directory_role.id SEPARATOR \', \')
                    FROM user_roles member_role
                    INNER JOIN roles directory_role ON directory_role.id = member_role.role_id
                    WHERE member_role.user_id = u.id
                ) AS role_labels,
                CASE
                    WHEN :batch_label_match IS NOT NULL AND mp.batch_id = :batch_reason THEN \'Same department and graduating year\'
                    WHEN :department_label_match IS NOT NULL AND mp.department_id = :department_reason THEN \'Same department\'
                    ELSE NULL
                END AS match_reason'
            . $from . $whereSql . '
             ORDER BY
                CASE
                    WHEN :batch_sort IS NOT NULL AND mp.batch_id = :batch_sort_match THEN 0
                    WHEN :department_sort IS NOT NULL AND mp.department_id = :department_sort_match THEN 1
                    ELSE 2
                END,
                u.full_name
             LIMIT :result_limit OFFSET :result_offset'
        );
        $statement->bindValue(':batch_reason', $suggestedBatchId, $suggestedBatchId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':department_reason', $suggestedDepartmentId, $suggestedDepartmentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        foreach ([
            'batch_sort' => $suggestedBatchId,
            'batch_sort_match' => $suggestedBatchId,
            'department_sort' => $suggestedDepartmentId,
            'department_sort_match' => $suggestedDepartmentId,
            'batch_label_match' => $suggestedBatchId,
            'department_label_match' => $suggestedDepartmentId,
        ] as $name => $value) {
            $statement->bindValue(':' . $name, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        }
        $statement->bindValue(':result_limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':result_offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return [
            'members' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'page_count' => $pageCount,
        ];
    }

    public function graduationYears(): array
    {
        return Connection::get()->query(
            'SELECT DISTINCT graduation_year
             FROM graduation_batches
             ORDER BY graduation_year DESC'
        )->fetchAll(PDO::FETCH_COLUMN);
    }
}
