<?php

declare(strict_types=1);

namespace AlumniConnect\Repositories;

use AlumniConnect\Database\Connection;
use PDO;

final class AdminRepository
{
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
}
