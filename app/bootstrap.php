<?php

declare(strict_types=1);

use AlumniConnect\Database\DatabaseUnavailable;
use AlumniConnect\Support\View;

const ALUMNICONNECT_ROOT = __DIR__ . '/..';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'");

spl_autoload_register(static function (string $class): void {
    $prefix = 'AlumniConnect\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = ALUMNICONNECT_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

set_exception_handler(static function (Throwable $exception): void {
    if ($exception instanceof DatabaseUnavailable) {
        error_log('[application] Protected request unavailable because the database is not ready.');
        View::error(503, 'Database setup required', $exception->getMessage());
        return;
    }

    error_log('[application] Unhandled request failure: ' . get_class($exception));
    if (!headers_sent()) {
        View::error(500, 'Something went wrong', 'The request could not be completed. Please try again.');
    }
});
