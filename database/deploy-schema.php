<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$host = getenv('DB_HOST');
$portValue = getenv('DB_PORT');
$databaseName = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$port = $portValue === false ? false : filter_var($portValue, FILTER_VALIDATE_INT);

if (
    !is_string($host)
    || $host === ''
    || strpbrk($host, ';=') !== false
    || !is_int($port)
    || $port < 1
    || $port > 65535
    || !is_string($databaseName)
    || !preg_match('/\A[a-zA-Z0-9_]{1,64}\z/', $databaseName)
    || !is_string($username)
    || $username === ''
    || !is_string($password)
    || $password === ''
    || $password === 'CHANGE_ME'
) {
    throw new RuntimeException('Set valid DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASSWORD deployment variables.');
}

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('PDO MySQL is required to initialize the deployment database.');
}

$multiStatementsAttribute = defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')
    ? constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')
    : constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$options[$multiStatementsAttribute] = true;

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $databaseName),
    $username,
    $password,
    $options
);

$tableCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
)->fetchColumn();
if ($tableCount !== 0) {
    throw new RuntimeException('The target database is not empty. Refusing to apply the initial schema.');
}

$schema = file_get_contents(__DIR__ . '/schema.sql');
if ($schema === false || trim($schema) === '') {
    throw new RuntimeException('The initial schema file is missing or empty.');
}

$pdo->exec($schema);
fwrite(STDOUT, "Initial AlumniConnect schema applied successfully.\n");
