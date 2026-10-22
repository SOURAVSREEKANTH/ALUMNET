<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('PDO MySQL is not enabled. Enable php_pdo_mysql.dll and rerun this setup.');
}

$configPath = dirname(__DIR__) . '/config/local.php';
if (is_file($configPath)) {
    throw new RuntimeException('config/local.php already exists. Refusing to overwrite local credentials.');
}

$host = getenv('MYSQL_ADMIN_HOST') ?: '127.0.0.1';
$portValue = getenv('MYSQL_ADMIN_PORT');
$port = $portValue === false || $portValue === '' ? 3306 : filter_var($portValue, FILTER_VALIDATE_INT);
$adminUser = getenv('MYSQL_ADMIN_USER') ?: 'root';
$adminPassword = getenv('MYSQL_ADMIN_PASSWORD');
$adminPassword = $adminPassword === false ? '' : $adminPassword;

if (!is_int($port) || $port < 1 || $port > 65535) {
    throw new RuntimeException('MYSQL_ADMIN_PORT must be a valid TCP port.');
}

$databaseName = getenv('APP_DB_NAME') ?: 'alumniconnect';
$appUsername = 'alumni_app';

if (!preg_match('/\A[a-zA-Z0-9_]{1,48}\z/', $databaseName)) {
    throw new RuntimeException('APP_DB_NAME may contain only letters, digits, and underscores.');
}

$adminDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
$multiStatementsAttribute = defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')
    ? constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')
    : constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
$adminOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 5,
];
$adminOptions[$multiStatementsAttribute] = true;
$admin = new PDO($adminDsn, $adminUser, $adminPassword, $adminOptions);
$admin->exec("SET time_zone = '+00:00'");

$databaseCheck = $admin->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = :name');
$databaseCheck->execute(['name' => $databaseName]);
if ((int) $databaseCheck->fetchColumn() !== 0) {
    throw new RuntimeException('The target database already exists. Choose a new APP_DB_NAME; existing data was not changed.');
}

$accountCheck = $admin->prepare('SELECT COUNT(*) FROM mysql.user WHERE user = :user AND host = :host');
$accountCheck->execute(['user' => $appUsername, 'host' => '127.0.0.1']);
if ((int) $accountCheck->fetchColumn() !== 0) {
    throw new RuntimeException('The local alumni_app database account already exists. Refusing to replace it.');
}

$quotedDatabase = '`' . $databaseName . '`';
$admin->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$schema = file_get_contents(__DIR__ . '/schema.sql');
if ($schema === false || trim($schema) === '') {
    throw new RuntimeException('The database schema file is missing or empty.');
}

$schemaConnection = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $databaseName),
    $adminUser,
    $adminPassword,
    $adminOptions
);
$schemaConnection->exec("SET time_zone = '+00:00'");
$schemaConnection->exec($schema);

$appPassword = bin2hex(random_bytes(32));
$quotedAppUser = $admin->quote($appUsername);
$quotedAppHost = $admin->quote('127.0.0.1');
$quotedAppPassword = $admin->quote($appPassword);
$admin->exec("CREATE USER {$quotedAppUser}@{$quotedAppHost} IDENTIFIED BY {$quotedAppPassword}");
$admin->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON {$quotedDatabase}.* TO {$quotedAppUser}@{$quotedAppHost}");

$localConfig = [
    'database' => [
        'host' => $host,
        'port' => $port,
        'name' => $databaseName,
        'username' => $appUsername,
        'password' => $appPassword,
        'charset' => 'utf8mb4',
    ],
    'app_key' => bin2hex(random_bytes(32)),
    'secure_cookies' => false,
];
$configContents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($localConfig, true) . ";\n";

if (file_put_contents($configPath, $configContents, LOCK_EX) === false) {
    throw new RuntimeException('Database setup completed, but config/local.php could not be written. Check project folder permissions.');
}

echo "AlumniConnect local database is ready. Local credentials were saved in the ignored config/local.php file.\n";
