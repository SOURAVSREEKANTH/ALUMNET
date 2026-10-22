<?php

declare(strict_types=1);

namespace AlumniConnect\Controllers;

use AlumniConnect\Repositories\AdminRepository;
use AlumniConnect\Security\Auth;
use AlumniConnect\Security\Session;
use AlumniConnect\Support\View;

final class AdminController
{
    private AdminRepository $admin;

    public function __construct()
    {
        $this->admin = new AdminRepository();
    }

    public function users(): void
    {
        $user = Auth::user();
        if ($user === null) {
            Session::flash('error', 'Sign in with an administrator account to review registrations.');
            View::redirect('/login');
        }
        if (!Auth::hasRole($user, 'admin')) {
            View::error(403, 'Administrator access required', 'Only administrators can review registered accounts.');
            return;
        }

        $queryValue = $_GET['q'] ?? '';
        $pageValue = $_GET['page'] ?? '1';
        $query = is_string($queryValue) ? trim($queryValue) : '';
        $queryLength = preg_match_all('/./us', $query);
        if (!is_string($queryValue) || $queryLength === false || $queryLength > 80) {
            http_response_code(422);
            $query = '';
            $error = 'Search must be 80 characters or fewer.';
        } else {
            $error = null;
        }

        $page = filter_var($pageValue, FILTER_VALIDATE_INT);
        if (!is_int($page) || $page < 1 || $page > 10000) {
            http_response_code(422);
            $page = 1;
            $error = 'The requested account-list page is invalid.';
        }

        $results = $this->admin->accounts($query, $page);
        View::render('pages/admin-users', [
            'pageTitle' => 'Account audit — AlumniConnect',
            'currentUser' => $user,
            'accounts' => $results['accounts'],
            'totalAccounts' => $results['total'],
            'page' => $results['page'],
            'pageCount' => $results['page_count'],
            'query' => $query,
            'auditError' => $error,
        ]);
    }
}
