<?php

declare(strict_types=1);

namespace AlumniConnect\Controllers;

use AlumniConnect\Repositories\EventRepository;
use AlumniConnect\Security\Auth;
use AlumniConnect\Security\Csrf;
use AlumniConnect\Security\Session;
use AlumniConnect\Support\View;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class EventController
{
    private EventRepository $events;

    public function __construct()
    {
        $this->events = new EventRepository();
    }

    public function index(): void
    {
        $user = $this->requireUser();
        $isAdmin = Auth::hasRole($user, 'admin');
        $isOrganizer = $isAdmin || Auth::hasRole($user, 'organizer');

        View::render('pages/events', [
            'pageTitle' => 'Community events — AlumniConnect',
            'currentUser' => $user,
            'events' => $this->formatEvents($this->events->upcomingForUser((int) $user['id'], $isAdmin)),
            'canPublishEvents' => $isOrganizer,
            'isEventOrganizer' => $isOrganizer,
            'formValues' => $this->emptyFormValues(),
            'formError' => null,
        ]);
    }

    public function publish(): void
    {
        $user = $this->requireUser();
        if (!Auth::hasRole($user, 'organizer') && !Auth::hasRole($user, 'admin')) {
            View::error(403, 'Organizer access required', 'Only event organizers and administrators can publish events.');
            return;
        }
        if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
            View::error(403, 'Request expired', 'Reload the page and submit the form again.');
            return;
        }

        $submittedValues = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'venue' => $_POST['venue'] ?? '',
            'starts_at' => $_POST['starts_at'] ?? '',
            'ends_at' => $_POST['ends_at'] ?? '',
            'capacity' => $_POST['capacity'] ?? '',
        ];
        $values = array_map(
            static fn (mixed $value): string => is_string($value) ? $value : '',
            $submittedValues
        );
        $errors = [];
        $title = $this->textField($submittedValues['title'], 'Event title', 4, 140, $errors);
        $description = $this->textField($submittedValues['description'], 'Description', 20, 4000, $errors, true);
        $venue = $this->textField($submittedValues['venue'], 'Venue', 2, 200, $errors);
        $startsAt = $this->dateTimeField($submittedValues['starts_at'], 'Start time', $errors);
        $endsAt = $this->dateTimeField($submittedValues['ends_at'], 'End time', $errors);
        $capacity = $this->capacityField($submittedValues['capacity'], $errors);

        if ($startsAt !== null && $startsAt <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            $errors[] = 'The event start time must be in the future.';
        }
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            $errors[] = 'The event end time must be after its start time.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->renderEvents(
                $user,
                implode(' ', $errors),
                $values
            );
            return;
        }

        $eventId = $this->events->publish((int) $user['id'], [
            'title' => $title,
            'description' => $description,
            'venue' => $venue,
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            'capacity' => $capacity,
        ]);

        Session::flash('success', 'Your event has been published and members can RSVP.');
        View::redirect('/events#event-' . $eventId);
    }

    public function rsvp(): void
    {
        $user = $this->requireUser();
        if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
            View::error(403, 'Request expired', 'Reload the page and submit the form again.');
            return;
        }

        $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $action = $_POST['action'] ?? null;
        if (!is_int($eventId) || !is_string($action) || !in_array($action, ['attend', 'interested', 'decline'], true)) {
            View::error(422, 'Invalid RSVP', 'Choose a valid event response and try again.');
            return;
        }

        try {
            $state = $this->events->respond($eventId, (int) $user['id'], $action);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/events');
        }

        $message = match ($state) {
            'attending' => 'Your place is confirmed.',
            'waitlisted' => 'The event is full. You have been added to its waitlist.',
            'interested' => 'Your interest has been recorded without reserving a place.',
            default => 'Your event response has been cancelled.',
        };
        Session::flash('success', $message);
        View::redirect('/events');
    }

    public function pass(): void
    {
        $user = $this->requireUser();
        if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
            View::error(403, 'Request expired', 'Reload the page and submit the form again.');
            return;
        }

        $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($eventId)) {
            View::error(422, 'Invalid event pass', 'Choose a valid event to issue a pass for.');
            return;
        }

        try {
            $issuedPass = $this->events->issuePass($eventId, (int) $user['id']);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/events');
        }

        $utc = new DateTimeZone('UTC');
        View::render('pages/event-pass', [
            'pageTitle' => 'Your event pass — AlumniConnect',
            'currentUser' => $user,
            'event' => $issuedPass['event'],
            'passToken' => $issuedPass['token'],
            'startsLabel' => (new DateTimeImmutable($issuedPass['event']['starts_at'], $utc))->format('l, F j, Y \a\t H:i \U\T\C'),
            'endsLabel' => (new DateTimeImmutable($issuedPass['event']['ends_at'], $utc))->format('l, F j, Y \a\t H:i \U\T\C'),
            'usesQrCode' => true,
            'usesEventTools' => true,
        ]);
    }

    public function checkInPage(): void
    {
        $user = $this->requireOrganizer();
        $eventId = filter_var($_GET['event'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($eventId)) {
            View::error(422, 'Choose an event', 'Select a valid event before opening its check-in desk.');
            return;
        }

        $event = $this->events->findManagedEvent(
            $eventId,
            (int) $user['id'],
            Auth::hasRole($user, 'admin')
        );
        if ($event === null) {
            View::error(404, 'Event not found', 'That event is not available for your account.');
            return;
        }

        $utc = new DateTimeZone('UTC');
        View::render('pages/event-check-in', [
            'pageTitle' => 'Event check-in — AlumniConnect',
            'currentUser' => $user,
            'event' => $event,
            'startsLabel' => (new DateTimeImmutable($event['starts_at'], $utc))->format('l, F j, Y \a\t H:i \U\T\C'),
            'endsLabel' => (new DateTimeImmutable($event['ends_at'], $utc))->format('l, F j, Y \a\t H:i \U\T\C'),
            'usesEventTools' => true,
        ]);
    }

    public function checkIn(): void
    {
        $user = $this->requireOrganizer();
        if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
            View::error(403, 'Request expired', 'Reload the page and submit the form again.');
            return;
        }

        $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $token = $_POST['pass_code'] ?? null;
        $method = $_POST['method'] ?? null;
        if (!is_int($eventId) || !is_string($token) || !is_string($method)) {
            View::error(422, 'Invalid check-in', 'Provide a valid event and pass code.');
            return;
        }

        try {
            $memberName = $this->events->checkIn(
                $eventId,
                (int) $user['id'],
                Auth::hasRole($user, 'admin'),
                trim($token),
                $method
            );
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
            View::redirect('/organizer/check-in?event=' . $eventId);
        }

        Session::flash('success', $memberName . ' is checked in.');
        View::redirect('/organizer/check-in?event=' . $eventId);
    }

    public function report(): void
    {
        $user = $this->requireOrganizer();
        $eventId = filter_var($_GET['event'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($eventId)) {
            View::error(422, 'Choose an event', 'Select a valid event before opening its attendance report.');
            return;
        }

        $report = $this->events->attendanceReport(
            $eventId,
            (int) $user['id'],
            Auth::hasRole($user, 'admin')
        );
        if ($report === null) {
            View::error(404, 'Event not found', 'That event is not available for your account.');
            return;
        }

        $utc = new DateTimeZone('UTC');
        View::render('pages/event-report', [
            'pageTitle' => 'Attendance report — AlumniConnect',
            'currentUser' => $user,
            'report' => $report,
            'startsLabel' => (new DateTimeImmutable($report['starts_at'], $utc))->format('l, F j, Y \a\t H:i \U\T\C'),
            'endsLabel' => (new DateTimeImmutable($report['ends_at'], $utc))->format('l, F j, Y \a\t H:i \U\T\C'),
        ]);
    }

    private function requireUser(): array
    {
        $user = Auth::user();
        if ($user === null) {
            Session::flash('error', 'Sign in to browse and RSVP to community events.');
            View::redirect('/login');
        }

        return $user;
    }

    private function requireOrganizer(): array
    {
        $user = $this->requireUser();
        if (!Auth::hasRole($user, 'organizer') && !Auth::hasRole($user, 'admin')) {
            View::error(403, 'Organizer access required', 'Only the event organizer or an administrator can use this tool.');
            exit;
        }

        return $user;
    }

    private function renderEvents(array $user, ?string $formError, array $formValues): void
    {
        $isAdmin = Auth::hasRole($user, 'admin');
        View::render('pages/events', [
            'pageTitle' => 'Community events — AlumniConnect',
            'currentUser' => $user,
            'events' => $this->formatEvents($this->events->upcomingForUser((int) $user['id'], $isAdmin)),
            'canPublishEvents' => Auth::hasRole($user, 'organizer') || $isAdmin,
            'isEventOrganizer' => Auth::hasRole($user, 'organizer') || $isAdmin,
            'formValues' => $formValues,
            'formError' => $formError,
        ]);
    }

    private function formatEvents(array $events): array
    {
        $utc = new DateTimeZone('UTC');
        foreach ($events as &$event) {
            $event['starts_label'] = (new DateTimeImmutable($event['starts_at'], $utc))->format('l, F j, Y \a\t H:i \U\T\C');
            $event['ends_label'] = (new DateTimeImmutable($event['ends_at'], $utc))->format('H:i \U\T\C');
            $event['attendee_count'] = (int) $event['attendee_count'];
            $event['waitlist_count'] = (int) $event['waitlist_count'];
            $event['my_waitlist_position'] = $event['my_waitlist_position'] === null
                ? null
                : (int) $event['my_waitlist_position'];
            $event['capacity'] = $event['capacity'] === null ? null : (int) $event['capacity'];
            $event['can_manage'] = (bool) $event['can_manage'];
        }
        unset($event);

        return $events;
    }

    private function emptyFormValues(): array
    {
        return [
            'title' => '',
            'description' => '',
            'venue' => '',
            'starts_at' => '',
            'ends_at' => '',
            'capacity' => '',
        ];
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
        $length = preg_match_all('/./us', $value);
        if ($length === false || $length < $minimum || $length > $maximum) {
            $errors[] = $label . ' must be between ' . $minimum . ' and ' . $maximum . ' characters.';
        }

        $controlPattern = $allowNewlines ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
        if (preg_match($controlPattern, $value) === 1) {
            $errors[] = $label . ' contains unsupported control characters.';
        }

        return $value;
    }

    private function dateTimeField(mixed $value, string $label, array &$errors): ?DateTimeImmutable
    {
        if (!is_string($value) || strlen($value) !== 16) {
            $errors[] = $label . ' must be a valid date and time in the required format.';
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('UTC'));
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $date->format('Y-m-d\TH:i') !== $value
        ) {
            $errors[] = $label . ' must be a valid date and time.';
            return null;
        }

        return $date;
    }

    private function capacityField(mixed $value, array &$errors): ?int
    {
        if ($value === '') {
            return null;
        }

        if (!is_string($value)) {
            $errors[] = 'Capacity must be a whole number between 1 and 10000, or blank for unlimited.';
            return null;
        }

        $capacity = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        if (!is_int($capacity)) {
            $errors[] = 'Capacity must be a whole number between 1 and 10000, or blank for unlimited.';
            return null;
        }

        return $capacity;
    }
}
