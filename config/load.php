<?php

declare(strict_types=1);

$example = require __DIR__ . '/config.example.php';
$localPath = __DIR__ . '/local.php';
$local = is_file($localPath) ? require $localPath : [];

if (!is_array($local)) {
    throw new RuntimeException('The local configuration file must return an array.');
}

$environment = [
    'database' => [
        'host' => getenv('DB_HOST') ?: null,
        'port' => getenv('DB_PORT') !== false ? (int) getenv('DB_PORT') : null,
        'name' => getenv('DB_NAME') ?: null,
        'username' => getenv('DB_USER') ?: null,
        'password' => getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : null,
    ],
    'app_key' => getenv('APP_KEY') ?: null,
    'secure_cookies' => getenv('SESSION_SECURE') !== false
        ? filter_var(getenv('SESSION_SECURE'), FILTER_VALIDATE_BOOLEAN)
        : null,
];

$mergeEnvironment = static function (array $base, array $overrides) use (&$mergeEnvironment): array {
    foreach ($overrides as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = $mergeEnvironment($base[$key], $value);
        } elseif ($value !== null) {
            $base[$key] = $value;
        }
    }

    return $base;
};

return $mergeEnvironment($mergeEnvironment($example, $local), $environment);
