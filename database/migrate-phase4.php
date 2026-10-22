<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$host = getenv('MYSQL_ADMIN_HOST') ?: '127.0.0.1';
$portValue = getenv('MYSQL_ADMIN_PORT');
$port = $portValue === false || $portValue === '' ? 3306 : filter_var($portValue, FILTER_VALIDATE_INT);
$adminUser = getenv('MYSQL_ADMIN_USER') ?: 'root';
$adminPassword = getenv('MYSQL_ADMIN_PASSWORD');
$adminPassword = $adminPassword === false ? '' : $adminPassword;
$database = require dirname(__DIR__) . '/config/load.php';
$database = $database['database'];

if (!is_int($port) || $port < 1 || $port > 65535) {
    throw new RuntimeException('MYSQL_ADMIN_PORT must be a valid TCP port.');
}

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('PDO MySQL is not enabled. Enable php_pdo_mysql.dll and rerun this migration.');
}

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database['name']
    ),
    $adminUser,
    $adminPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]
);
$pdo->exec("SET time_zone = '+00:00'");
$schema = file_get_contents(__DIR__ . '/migrations/phase4-member-profiles.sql');
if ($schema === false || trim($schema) === '') {
    throw new RuntimeException('The Phase 4 database migration is missing or empty.');
}

$pdo->exec($schema);

$backfill = $pdo->exec(
    'INSERT IGNORE INTO member_profiles (
        user_id,
        department_id,
        batch_id,
        headline,
        company,
        job_title,
        biography,
        profile_visibility,
        contact_sharing,
        created_at,
        updated_at
     )
     SELECT
        u.id,
        ap.department_id,
        ap.batch_id,
        ap.headline,
        ap.company,
        ap.job_title,
        ap.biography,
        COALESCE(ap.profile_visibility, \'members\'),
        COALESCE(ap.contact_sharing, 0),
        COALESCE(ap.created_at, u.created_at),
        COALESCE(ap.updated_at, u.updated_at)
     FROM users u
     LEFT JOIN alumni_profiles ap ON ap.user_id = u.id'
);

echo 'Phase 4 profile migration complete. Backfilled ' . $backfill . " member profile(s).\n";
