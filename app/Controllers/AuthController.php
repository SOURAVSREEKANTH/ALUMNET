<?php

declare(strict_types=1);

namespace AlumniConnect\Controllers;

use AlumniConnect\Repositories\DuplicateAccountException;
use AlumniConnect\Repositories\UserRepository;
use AlumniConnect\Security\Auth;
use AlumniConnect\Security\Csrf;
use AlumniConnect\Security\LoginRateLimiter;
use AlumniConnect\Security\Session;
use AlumniConnect\Services\AuthenticationService;
use AlumniConnect\Services\LoginResult;
use AlumniConnect\Support\View;

final class AuthController
{
    private AuthenticationService $authentication;
    private LoginRateLimiter $rateLimiter;

    public function __construct()
    {
        $this->rateLimiter = new LoginRateLimiter();
        $this->authentication = new AuthenticationService(new UserRepository(), $this->rateLimiter);
    }

    public function showLogin(): void
    {
        if (Auth::user() !== null) {
            View::redirect('/dashboard');
        }

        $this->renderLogin();
    }

    public function login(): void
    {
        if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
            View::error(403, 'Request expired', 'Reload the page and submit the form again.');
            return;
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        if (!is_string($email) || !is_string($password) || strlen($email) > 254 || strlen($password) > 128) {
            http_response_code(422);
            $this->renderLogin('Enter a valid email address and password.');
            return;
        }

        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $password === '') {
            http_response_code(422);
            $this->renderLogin('Enter a valid email address and password.', $email);
            return;
        }

        $result = $this->authentication->login(
            $email,
            $password,
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );

        if ($result === LoginResult::Authenticated) {
            View::redirect('/dashboard');
        }

        if ($result === LoginResult::Throttled) {
            http_response_code(429);
            $this->renderLogin('Too many sign-in attempts. Wait 15 minutes before trying again.', $email);
            return;
        }

        http_response_code(401);
        $this->renderLogin('The email or password was not recognized.', $email);
    }

    public function showRegistration(): void
    {
        if (Auth::user() !== null) {
            View::redirect('/dashboard');
        }

        $this->renderRegistration();
    }

    public function register(): void
    {
        if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
            View::error(403, 'Request expired', 'Reload the page and submit the form again.');
            return;
        }

        $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if ($this->rateLimiter->isBlocked('registration', null, $remoteAddress)) {
            http_response_code(429);
            $this->renderRegistration('Too many registration attempts. Wait 15 minutes before trying again.');
            return;
        }
        $this->rateLimiter->record('registration', null, $remoteAddress);

        $fullName = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmation = $_POST['password_confirmation'] ?? '';
        $role = $_POST['account_type'] ?? '';

        $errors = [];
        if (!is_string($fullName) || !self::validName($fullName)) {
            $errors[] = 'Enter a name between 2 and 100 characters.';
        }
        if (!is_string($email) || strlen($email) > 254 || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!is_string($password) || strlen($password) < 12 || strlen($password) > 128) {
            $errors[] = 'Choose a password between 12 and 128 characters.';
        }
        if (!is_string($confirmation) || !is_string($password) || !hash_equals($password, $confirmation)) {
            $errors[] = 'The password confirmation does not match.';
        }
        if (!is_string($role) || !in_array($role, ['alumni', 'student'], true)) {
            $errors[] = 'Choose either the alumni or student account type.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->renderRegistration(implode(' ', $errors), is_string($fullName) ? $fullName : '', is_string($email) ? $email : '');
            return;
        }

        try {
            $userId = $this->authentication->register($fullName, $email, $password, $role);
        } catch (DuplicateAccountException) {
            http_response_code(422);
            $this->renderRegistration(
                'An account may already use that email. Try signing in or use a different address.',
                $fullName,
                $email
            );
            return;
        }

        Session::authenticate($userId);
        if ($role === 'alumni') {
            Session::flash('success', 'Account created. Your alumni status remains pending until an administrator verifies it.');
        } else {
            Session::flash('success', 'Your account is ready.');
        }

        View::redirect('/dashboard');
    }

    public function logout(): void
    {
        if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
            View::error(403, 'Request expired', 'Reload the page and submit the form again.');
            return;
        }

        Session::logout();
        View::redirect('/');
    }

    private function renderLogin(?string $error = null, string $email = ''): void
    {
        View::render('pages/login', [
            'pageTitle' => 'Sign in — AlumniConnect',
            'formError' => $error,
            'email' => $email,
        ]);
    }

    private function renderRegistration(?string $error = null, string $fullName = '', string $email = ''): void
    {
        View::render('pages/register', [
            'pageTitle' => 'Join AlumniConnect',
            'formError' => $error,
            'fullName' => $fullName,
            'email' => $email,
        ]);
    }

    private static function validName(string $name): bool
    {
        $name = trim($name);
        $characterCount = preg_match_all('/./us', $name);

        return $characterCount !== false
            && $characterCount >= 2
            && $characterCount <= 100
            && preg_match('/[\x00-\x1F\x7F]/u', $name) !== 1;
    }
}
