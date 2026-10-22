<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use AlumniConnect\Controllers\AuthController;
use AlumniConnect\Controllers\AdminController;
use AlumniConnect\Controllers\CommunityController;
use AlumniConnect\Controllers\EventController;
use AlumniConnect\Controllers\MemoryController;
use AlumniConnect\Controllers\NotificationController;
use AlumniConnect\Controllers\PageController;
use AlumniConnect\Controllers\ProfileController;
use AlumniConnect\Support\View;

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (!is_string($requestPath) || str_contains($requestPath, "\0")) {
    View::error(400, 'Invalid request', 'The requested address is not valid.');
    return;
}

$path = '/' . trim(rawurldecode($requestPath), '/');
if ($path !== '/') {
    $path = rtrim($path, '/');
}

$pages = new PageController();
$events = new EventController();
$profiles = new ProfileController();
$community = new CommunityController();
$admin = new AdminController();
$memories = new MemoryController();
$notifications = new NotificationController();
$authentication = null;
$routes = [
    'GET' => [
        '/' => [$pages, 'home'],
        '/dashboard' => [$pages, 'dashboard'],
        '/profile' => [$profiles, 'edit'],
        '/directory' => [$profiles, 'directory'],
        '/events' => [$events, 'index'],
        '/community' => [$community, 'index'],
        '/memory-lane' => [$memories, 'index'],
        '/memory/photo' => [$memories, 'photo'],
        '/notifications' => [$notifications, 'index'],
        '/admin/users' => [$admin, 'users'],
        '/admin/operations' => [$admin, 'operations'],
        '/organizer/check-in' => [$events, 'checkInPage'],
        '/organizer/report' => [$events, 'report'],
        '/login' => [static function () use (&$authentication): void {
            $authentication ??= new AuthController();
            $authentication->showLogin();
        }],
        '/register' => [static function () use (&$authentication): void {
            $authentication ??= new AuthController();
            $authentication->showRegistration();
        }],
    ],
    'POST' => [
        '/login' => [static function () use (&$authentication): void {
            $authentication ??= new AuthController();
            $authentication->login();
        }],
        '/register' => [static function () use (&$authentication): void {
            $authentication ??= new AuthController();
            $authentication->register();
        }],
        '/logout' => [static function () use (&$authentication): void {
            $authentication ??= new AuthController();
            $authentication->logout();
        }],
        '/profile' => [$profiles, 'save'],
        '/events/create' => [$events, 'publish'],
        '/events/rsvp' => [$events, 'rsvp'],
        '/events/pass' => [$events, 'pass'],
        '/events/discussion' => [$memories, 'addDiscussion'],
        '/events/discussion/delete' => [$memories, 'deleteDiscussion'],
        '/events/photo' => [$memories, 'uploadPhoto'],
        '/events/photo/delete' => [$memories, 'deletePhoto'],
        '/notifications/read' => [$notifications, 'markAllRead'],
        '/organizer/check-in' => [$events, 'checkIn'],
        '/community/mentor-profile' => [$community, 'saveMentorProfile'],
        '/mentorship/request' => [$community, 'requestMentorship'],
        '/mentorship/decision' => [$community, 'decideMentorshipRequest'],
        '/opportunities/create' => [$community, 'publishOpportunity'],
        '/opportunities/close' => [$community, 'closeOpportunity'],
        '/community/preferences' => [$community, 'saveEventPreferences'],
        '/admin/alumni-review' => [$admin, 'reviewAlumnus'],
        '/admin/photo-review' => [$admin, 'reviewPhoto'],
        '/admin/photo-delete' => [$admin, 'deletePhoto'],
        '/admin/account-status' => [$admin, 'setAccountStatus'],
        '/admin/account-role' => [$admin, 'changeAccountRole'],
    ],
];

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$handler = $routes[$method][$path] ?? null;

if ($handler === null) {
    $allowedMethods = [];
    foreach ($routes as $routeMethod => $methodRoutes) {
        if (array_key_exists($path, $methodRoutes)) {
            $allowedMethods[] = $routeMethod;
        }
    }

    if ($allowedMethods !== []) {
        header('Allow: ' . implode(', ', $allowedMethods));
        View::error(405, 'Method not allowed', 'Use the supported method for this action.');
        return;
    }

    View::error(404, 'Page not found', 'That page is not available yet.');
    return;
}

if (count($handler) === 1 && is_callable($handler[0])) {
    $handler[0]();
    return;
}

[$controller, $action] = $handler;
$controller->{$action}();
