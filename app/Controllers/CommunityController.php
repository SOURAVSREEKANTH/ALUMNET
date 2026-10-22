<?php

declare(strict_types=1);

namespace AlumniConnect\Controllers;

use AlumniConnect\Repositories\CommunityRepository;
use AlumniConnect\Repositories\ProfileRepository;
use AlumniConnect\Security\Auth;
use AlumniConnect\Security\Csrf;
use AlumniConnect\Security\Session;
use AlumniConnect\Support\View;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class CommunityController
{
    private CommunityRepository $community;
    private ProfileRepository $profiles;

    public function __construct()
    {
        $this->community = new CommunityRepository();
        $this->profiles = new ProfileRepository();
    }

    public function index(): void
    {
        $user = $this->requireUser();
        $isAdmin = Auth::hasRole($user, 'admin');
        $isStudent = Auth::hasRole($user, 'student');
        $verifiedAlumnus = $this->community->isVerifiedAlumnus((int) $user['id']);

        View::render('pages/community', [
            'pageTitle' => 'Mentorship and opportunities — AlumniConnect',
            'currentUser' => $user,
            'mentors' => $this->community->mentors((int) $user['id']),
            'mentorProfile' => $this->community->mentorProfile((int) $user['id']),
            'requests' => $this->community->mentorshipRequests((int) $user['id']),
            'opportunities' => $this->community->opportunities((int) $user['id'], $isAdmin),
            'canOfferMentorship' => $verifiedAlumnus,
            'canRequestMentorship' => $isStudent,
            'canPublishOpportunities' => $verifiedAlumnus
                || Auth::hasRole($user, 'organizer')
                || $isAdmin,
            'eventPreferences' => $this->community->eventPreferences((int) $user['id']),
            'matchingEvents' => $this->community->matchingEvents((int) $user['id']),
        ]);
    }

    public function saveMentorProfile(): void
    {
        $user = $this->requireUser();
        $this->requireCsrf();
        if (!$this->community->isVerifiedAlumnus((int) $user['id'])) {
            View::error(403, 'Verified alumni access required', 'Only alumni verified by an administrator can offer mentorship.');
            return;
        }

        $topics = $this->text($_POST['topics'] ?? null, 3, 500, 'Mentorship topics');
        $availability = $_POST['is_available'] ?? null;
        if (!in_array($availability, ['0', '1'], true)) {
            $this->invalidInput('Choose whether your mentorship profile is available.');
        }

        try {
            $this->community->saveMentorProfile((int) $user['id'], $topics, $availability === '1');
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/community#mentorship');
        }
        Session::flash('success', $availability === '1'
            ? 'Your mentor profile is now available to students.'
            : 'Your mentor profile is paused; new requests are disabled.');
        View::redirect('/community#mentorship');
    }

    public function requestMentorship(): void
    {
        $user = $this->requireUser();
        $this->requireCsrf();
        $mentorId = $this->positiveId($_POST['mentor_id'] ?? null, 'mentor');
        $goals = $this->text($_POST['goals'] ?? null, 20, 2000, 'Mentorship goals');

        try {
            $this->community->requestMentorship((int) $user['id'], $mentorId, $goals);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/community#mentorship');
        }

        Session::flash('success', 'Your mentorship request has been sent.');
        View::redirect('/community#requests');
    }

    public function decideMentorshipRequest(): void
    {
        $user = $this->requireUser();
        $this->requireCsrf();
        $requestId = $this->positiveId($_POST['request_id'] ?? null, 'request');
        $decision = $_POST['decision'] ?? null;
        if (!is_string($decision) || !in_array($decision, ['accepted', 'declined'], true)) {
            $this->invalidInput('Choose whether to accept or decline this request.');
        }

        try {
            $this->community->decideMentorshipRequest((int) $user['id'], $requestId, $decision);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/community#requests');
        }

        Session::flash('success', 'The mentorship request was ' . $decision . '.');
        View::redirect('/community#requests');
    }

    public function publishOpportunity(): void
    {
        $user = $this->requireUser();
        $this->requireCsrf();
        if (!$this->community->canPublishOpportunities((int) $user['id'])) {
            View::error(403, 'Opportunity publisher access required', 'Only verified alumni, organizers, and administrators can publish opportunities.');
            return;
        }

        $title = $this->text($_POST['title'] ?? null, 4, 160, 'Opportunity title');
        $type = $_POST['opportunity_type'] ?? null;
        if (!is_string($type) || !in_array($type, ['job', 'internship', 'scholarship', 'volunteer', 'other'], true)) {
            $this->invalidInput('Choose a valid opportunity type.');
        }
        $description = $this->text($_POST['description'] ?? null, 20, 4000, 'Description', true);
        $location = $this->text($_POST['location'] ?? '', 0, 160, 'Location');
        $expiresAt = $this->optionalDateTime($_POST['expires_at'] ?? '', 'Closing date');

        try {
            $opportunityId = $this->community->publishOpportunity((int) $user['id'], [
                'title' => $title,
                'type' => $type,
                'description' => $description,
                'location' => $location === '' ? null : $location,
                'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            ]);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/community#opportunities');
        }

        Session::flash('success', 'Your opportunity has been published.');
        View::redirect('/community#opportunity-' . $opportunityId);
    }

    public function closeOpportunity(): void
    {
        $user = $this->requireUser();
        $this->requireCsrf();
        $opportunityId = $this->positiveId($_POST['opportunity_id'] ?? null, 'opportunity');

        try {
            $this->community->closeOpportunity(
                (int) $user['id'],
                $opportunityId,
                Auth::hasRole($user, 'admin')
            );
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/community#opportunities');
        }

        Session::flash('success', 'The opportunity is now closed.');
        View::redirect('/community#opportunities');
    }

    public function saveEventPreferences(): void
    {
        $user = $this->requireUser();
        $this->requireCsrf();
        $location = $this->text($_POST['preferred_location'] ?? '', 0, 120, 'Location preference');
        $availableFrom = $this->optionalDate($_POST['available_from'] ?? '', 'Available from');
        $availableUntil = $this->optionalDate($_POST['available_until'] ?? '', 'Available until');
        if (
            $availableFrom !== null
            && $availableUntil !== null
            && $availableUntil < $availableFrom
        ) {
            $this->invalidInput('The end of your availability window must not be before its start.');
        }

        $this->community->saveEventPreferences((int) $user['id'], [
            'preferred_location' => $location === '' ? null : $location,
            'available_from' => $availableFrom,
            'available_until' => $availableUntil,
        ]);
        Session::flash('success', 'Your meetup preferences have been saved.');
        View::redirect('/community#meetups');
    }

    private function requireUser(): array
    {
        $user = Auth::user();
        if ($user === null) {
            Session::flash('error', 'Sign in to access mentorship and community opportunities.');
            View::redirect('/login');
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
            $this->invalidInput('Choose a valid ' . $label . '.');
        }

        return $id;
    }

    private function text(
        mixed $value,
        int $minimum,
        int $maximum,
        string $label,
        bool $allowNewlines = false
    ): string {
        if (!is_string($value)) {
            $this->invalidInput($label . ' must be plain text.');
        }

        $value = trim($value);
        $length = preg_match_all('/./us', $value);
        $length = $length === false ? PHP_INT_MAX : $length;
        if ($length < $minimum || $length > $maximum) {
            $this->invalidInput(
                $label . ' must be between ' . $minimum . ' and ' . $maximum . ' characters.'
            );
        }

        $controlPattern = $allowNewlines
            ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'
            : '/[\x00-\x1F\x7F]/u';
        if (preg_match($controlPattern, $value) === 1) {
            $this->invalidInput($label . ' contains unsupported control characters.');
        }

        return $value;
    }

    private function optionalDate(mixed $value, string $label): ?string
    {
        if ($value === '') {
            return null;
        }
        if (!is_string($value)) {
            $this->invalidInput($label . ' must be a valid date.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (
            !$date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            $this->invalidInput($label . ' must be a valid date.');
        }

        return $date->format('Y-m-d');
    }

    private function optionalDateTime(mixed $value, string $label): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        if (!is_string($value)) {
            $this->invalidInput($label . ' must be a valid date and time.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (
            !$date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d\TH:i') !== $value
        ) {
            $this->invalidInput($label . ' must be a valid date and time.');
        }
        if ($date <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            $this->invalidInput($label . ' must be in the future.');
        }

        return $date;
    }

    private function invalidInput(string $message): never
    {
        View::error(422, 'Invalid request', $message);
        exit;
    }
}
