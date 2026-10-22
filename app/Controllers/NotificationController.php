<?php

declare(strict_types=1);

namespace AlumniConnect\Controllers;

use AlumniConnect\Repositories\NotificationRepository;
use AlumniConnect\Security\Auth;
use AlumniConnect\Security\Csrf;
use AlumniConnect\Security\Session;
use AlumniConnect\Support\View;

final class NotificationController
{
    private NotificationRepository $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationRepository();
    }

    public function index(): void
    {
        $user = $this->requireUser();
        View::render('pages/notifications', [
            'pageTitle' => 'Notifications — AlumniConnect',
            'currentUser' => $user,
            'notifications' => $this->notifications->forUser((int) $user['id']),
        ]);
    }

    public function markAllRead(): void
    {
        $user = $this->requireUser();
        if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
            View::error(403, 'Request expired', 'Reload the page and submit the form again.');
            return;
        }

        $this->notifications->markAllRead((int) $user['id']);
        Session::flash('success', 'Your notifications are marked as read.');
        View::redirect('/notifications');
    }

    private function requireUser(): array
    {
        $user = Auth::user();
        if ($user === null) {
            Session::flash('error', 'Sign in to view your notifications.');
            View::redirect('/login');
        }

        return $user;
    }
}
