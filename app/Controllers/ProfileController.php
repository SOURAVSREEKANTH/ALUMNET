<?php

declare(strict_types=1);

namespace AlumniConnect\Controllers;

use AlumniConnect\Repositories\ProfileRepository;
use AlumniConnect\Security\Auth;
use AlumniConnect\Security\Csrf;
use AlumniConnect\Security\Session;
use AlumniConnect\Support\View;
use InvalidArgumentException;

final class ProfileController
{
    private ProfileRepository $profiles;

    public function __construct()
    {
        $this->profiles = new ProfileRepository();
    }

    public function edit(): void
    {
        $user = $this->requireUser();
        $this->renderProfile($user);
    }

    public function save(): void
    {
        $user = $this->requireUser();
        if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
            View::error(403, 'Request expired', 'Reload the page and submit the form again.');
            return;
        }

        $values = [
            'full_name' => $_POST['full_name'] ?? '',
            'department_id' => $_POST['department_id'] ?? '',
            'batch_id' => $_POST['batch_id'] ?? '',
            'headline' => $_POST['headline'] ?? '',
            'company' => $_POST['company'] ?? '',
            'job_title' => $_POST['job_title'] ?? '',
            'biography' => $_POST['biography'] ?? '',
            'profile_visibility' => $_POST['profile_visibility'] ?? '',
            'contact_sharing' => $_POST['contact_sharing'] ?? '',
        ];

        $errors = [];
        $fullName = $this->textField($values['full_name'], 'Full name', 2, 100, $errors);
        $headline = $this->textField($values['headline'], 'Headline', 0, 160, $errors);
        $company = $this->textField($values['company'], 'Company', 0, 160, $errors);
        $jobTitle = $this->textField($values['job_title'], 'Job title', 0, 160, $errors);
        $biography = $this->textField($values['biography'], 'About you', 0, 2000, $errors, true);
        $departmentId = $this->optionalId($values['department_id'], 'Department', $errors);
        $batchId = $this->optionalId($values['batch_id'], 'Graduating year', $errors);
        $visibility = is_string($values['profile_visibility']) ? $values['profile_visibility'] : '';
        if (!in_array($visibility, ['private', 'members', 'public'], true)) {
            $errors[] = 'Choose a valid profile visibility setting.';
        }
        $contactSharing = is_string($values['contact_sharing']) ? $values['contact_sharing'] : '';
        if (!in_array($contactSharing, ['0', '1'], true)) {
            $errors[] = 'Choose a valid contact-sharing setting.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->renderProfile($user, implode(' ', $errors), $values);
            return;
        }

        try {
            $this->profiles->save((int) $user['id'], [
                'full_name' => $fullName,
                'department_id' => $departmentId,
                'batch_id' => $batchId,
                'headline' => $headline === '' ? null : $headline,
                'company' => $company === '' ? null : $company,
                'job_title' => $jobTitle === '' ? null : $jobTitle,
                'biography' => $biography === '' ? null : $biography,
                'profile_visibility' => $visibility,
                'contact_sharing' => (int) $contactSharing,
            ]);
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $this->renderProfile($user, $exception->getMessage(), $values);
            return;
        }

        Session::flash('success', 'Your profile has been saved.');
        View::redirect('/profile');
    }

    public function directory(): void
    {
        $user = $this->requireUser();
        $queryValue = $_GET['q'] ?? '';
        $departmentValue = $_GET['department'] ?? '';
        $yearValue = $_GET['year'] ?? '';
        $pageValue = $_GET['page'] ?? '1';
        $errors = [];

        $query = is_string($queryValue) ? trim($queryValue) : '';
        if (!is_string($queryValue) || $this->characterCount($query) > 80) {
            $errors[] = 'Search must be 80 characters or fewer.';
        }

        $departmentId = $this->optionalId($departmentValue, 'Department filter', $errors);
        $graduationYear = $this->optionalId($yearValue, 'Graduating year filter', $errors);
        $page = filter_var($pageValue, FILTER_VALIDATE_INT);
        if (!is_int($page) || $page < 1 || $page > 10000) {
            $errors[] = 'The requested directory page is invalid.';
            $page = 1;
        }

        if ($errors !== []) {
            http_response_code(422);
        }

        $profile = $this->profiles->findForUser((int) $user['id']);
        $results = $errors === []
            ? $this->profiles->search(
                (int) $user['id'],
                $departmentId,
                $graduationYear,
                $query,
                $page,
                $profile['department_id'] === null ? null : (int) $profile['department_id'],
                $profile['batch_id'] === null ? null : (int) $profile['batch_id']
            )
            : ['members' => [], 'total' => 0, 'page' => 1, 'page_count' => 1];

        View::render('pages/directory', [
            'pageTitle' => 'Member directory — AlumniConnect',
            'currentUser' => $user,
            'members' => $results['members'],
            'totalMembers' => $results['total'],
            'page' => $results['page'],
            'pageCount' => $results['page_count'],
            'departments' => $this->profiles->departments(),
            'graduationYears' => $this->profiles->graduationYears(),
            'query' => $query,
            'selectedDepartment' => $departmentId,
            'selectedYear' => $graduationYear,
            'directoryError' => $errors === [] ? null : implode(' ', $errors),
        ]);
    }

    private function renderProfile(array $user, ?string $error = null, ?array $values = null): void
    {
        $profile = $this->profiles->findForUser((int) $user['id']);
        if ($values !== null) {
            $profile = array_merge($profile, $values);
        }

        View::render('pages/profile', [
            'pageTitle' => 'Edit your profile — AlumniConnect',
            'currentUser' => $user,
            'profile' => $profile,
            'formValues' => $values ?? $profile,
            'departments' => $this->profiles->departments(),
            'batches' => $this->profiles->batches(),
            'formError' => $error,
        ]);
    }

    private function requireUser(): array
    {
        $user = Auth::user();
        if ($user === null) {
            Session::flash('error', 'Sign in to access member profiles.');
            View::redirect('/login');
        }

        return $user;
    }

    private function textField(
        mixed $value,
        string $label,
        int $minimum,
        int $maximum,
        array &$errors,
        bool $allowNewlines = false
    ): string {
        if (!is_string($value)) {
            $errors[] = $label . ' must be plain text.';
            return '';
        }

        $value = trim($value);
        $length = $this->characterCount($value);
        if ($length < $minimum || $length > $maximum) {
            $errors[] = $label . ' must be between ' . $minimum . ' and ' . $maximum . ' characters.';
        }

        $controlPattern = $allowNewlines ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
        if (preg_match($controlPattern, $value) === 1) {
            $errors[] = $label . ' contains unsupported control characters.';
        }

        return $value;
    }

    private function optionalId(mixed $value, string $label, array &$errors): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }
        if (!is_string($value) && !is_int($value)) {
            $errors[] = 'Choose a valid ' . strtolower($label) . '.';
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($id)) {
            $errors[] = 'Choose a valid ' . strtolower($label) . '.';
            return null;
        }

        return $id;
    }

    private function characterCount(string $value): int
    {
        $count = preg_match_all('/./us', $value);
        return $count === false ? PHP_INT_MAX : $count;
    }
}
