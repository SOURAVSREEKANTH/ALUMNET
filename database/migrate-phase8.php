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
$configuration = require dirname(__DIR__) . '/config/load.php';
$database = $configuration['database'];

if (!is_int($port) || $port < 1 || $port > 65535) {
    throw new RuntimeException('MYSQL_ADMIN_PORT must be a valid TCP port.');
}

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('PDO MySQL is not enabled. Enable php_pdo_mysql.dll and rerun this migration.');
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database['name']),
    $adminUser,
    $adminPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]
);
$pdo->exec("SET time_zone = '+00:00'");
$schema = file_get_contents(__DIR__ . '/migrations/phase8-community-content.sql');
if ($schema === false || trim($schema) === '') {
    throw new RuntimeException('The Phase 8 database migration is missing or empty.');
}

$pdo->exec($schema);

echo "Phase 8 discussions, private event-photo storage metadata, and notifications migration complete.\n";
