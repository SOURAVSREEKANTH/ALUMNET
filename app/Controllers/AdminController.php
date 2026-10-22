<?php

declare(strict_types=1);

namespace AlumniConnect\Controllers;

use AlumniConnect\Repositories\AdminRepository;
use AlumniConnect\Security\Csrf;
use AlumniConnect\Security\Auth;
use AlumniConnect\Security\Session;
use AlumniConnect\Services\MemoryUploadService;
use AlumniConnect\Support\View;
use InvalidArgumentException;

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
            'roles' => $this->admin->roles(),
        ]);
    }

    public function auditLog(): void
    {
        $user = $this->requireAdmin();
        $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT);
        if (!is_int($page) || $page < 1 || $page > 10000) {
            View::error(422, 'Invalid audit page', 'Choose a valid page from the audit log.');
            return;
        }

        $results = $this->admin->auditEntries($page);
        View::render('pages/admin-audit-log', [
            'pageTitle' => 'Audit log — AlumniConnect',
            'currentUser' => $user,
            'entries' => $results['entries'],
            'totalEntries' => $results['total'],
            'page' => $results['page'],
            'pageCount' => $results['page_count'],
        ]);
    }

    public function setAccountStatus(): void
    {
        $user = $this->requireAdmin();
        $this->requireCsrf();
        $targetUserId = $this->positiveId($_POST['user_id'] ?? null, 'account');
        $status = $_POST['status'] ?? null;
        if (!is_string($status) || !in_array($status, ['active', 'suspended'], true)) {
            View::error(422, 'Invalid account status', 'Choose whether to activate or suspend the account.');
            return;
        }

        try {
            $this->admin->setAccountStatus((int) $user['id'], $targetUserId, $status);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/admin/users');
        }
        Session::flash('success', 'The account status was updated and audited.');
        View::redirect('/admin/users');
    }

    public function changeAccountRole(): void
    {
        $user = $this->requireAdmin();
        $this->requireCsrf();
        $targetUserId = $this->positiveId($_POST['user_id'] ?? null, 'account');
        $role = $_POST['role'] ?? null;
        $action = $_POST['action'] ?? null;
        if (!is_string($role) || !is_string($action) || !in_array($action, ['grant', 'revoke'], true)) {
            View::error(422, 'Invalid role change', 'Choose a valid account role and action.');
            return;
        }

        try {
            $this->admin->changeAccountRole((int) $user['id'], $targetUserId, $role, $action);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/admin/users');
        }
        Session::flash('success', 'The account role was updated and audited.');
        View::redirect('/admin/users');
    }

    public function operations(): void
    {
        $user = $this->requireAdmin();
        View::render('pages/admin-operations', [
            'pageTitle' => 'Administration — AlumniConnect',
            'currentUser' => $user,
            'summary' => $this->admin->operationalSummary(),
            'pendingAlumni' => $this->admin->pendingAlumni(),
            'pendingPhotos' => $this->admin->pendingPhotos(),
        ]);
    }

    public function reviewAlumnus(): void
    {
        $user = $this->requireAdmin();
        $this->requireCsrf();
        $userId = $this->positiveId($_POST['user_id'] ?? null, 'account');
        $decision = $_POST['decision'] ?? null;
        if (!is_string($decision) || !in_array($decision, ['verified', 'rejected'], true)) {
            View::error(422, 'Invalid verification decision', 'Choose whether to verify or reject this alumni account.');
            return;
        }

        try {
            $this->admin->reviewAlumnus((int) $user['id'], $userId, $decision);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/admin/operations');
        }
        Session::flash('success', 'The alumni verification decision was recorded and audited.');
        View::redirect('/admin/operations');
    }

    public function reviewPhoto(): void
    {
        $user = $this->requireAdmin();
        $this->requireCsrf();
        $photoId = $this->positiveId($_POST['photo_id'] ?? null, 'photo');
        $decision = $_POST['decision'] ?? null;
        if (!is_string($decision) || !in_array($decision, ['approved', 'rejected'], true)) {
            View::error(422, 'Invalid moderation decision', 'Choose whether to approve or reject this photo.');
            return;
        }

        try {
            $this->admin->reviewPhoto((int) $user['id'], $photoId, $decision);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/admin/operations');
        }
        Session::flash('success', 'The photo moderation decision was recorded and audited.');
        View::redirect('/admin/operations');
    }

    public function deletePhoto(): void
    {
        $user = $this->requireAdmin();
        $this->requireCsrf();
        $photoId = $this->positiveId($_POST['photo_id'] ?? null, 'photo');

        try {
            (new MemoryUploadService())->delete($photoId, (int) $user['id'], true);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/admin/operations');
        }
        Session::flash('success', 'The photo and private file were removed.');
        View::redirect('/admin/operations');
    }

    private function requireAdmin(): array
    {
        $user = Auth::user();
        if ($user === null) {
            Session::flash('error', 'Sign in with an administrator account to access operations.');
            View::redirect('/login');
        }
        if (!Auth::hasRole($user, 'admin')) {
            View::error(403, 'Administrator access required', 'Only administrators can use this page.');
            exit;
        }

        return $user;
    }

    private function requireCsrf(): void
    {
        if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
            View::error(403, 'Request expired', 'Reload the page and submit the form again.');
            exit;
        }
    }

    private function positiveId(mixed $value, string $label): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($id)) {
            View::error(422, 'Invalid selection', 'Choose a valid ' . $label . '.');
            exit;
        }

        return $id;
    }
}
