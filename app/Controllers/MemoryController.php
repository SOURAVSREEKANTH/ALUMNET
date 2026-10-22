<?php

declare(strict_types=1);

namespace AlumniConnect\Controllers;

use AlumniConnect\Repositories\MemoryRepository;
use AlumniConnect\Security\Auth;
use AlumniConnect\Security\Csrf;
use AlumniConnect\Security\Session;
use AlumniConnect\Services\MemoryUploadService;
use AlumniConnect\Support\View;
use InvalidArgumentException;
use RuntimeException;

final class MemoryController
{
    private MemoryRepository $memories;
    private MemoryUploadService $uploads;

    public function __construct()
    {
        $this->memories = new MemoryRepository();
        $this->uploads = new MemoryUploadService();
    }

    public function index(): void
    {
        $user = $this->requireUser();
        $isAdmin = Auth::hasRole($user, 'admin');
        $events = $this->memories->pastEvents();
        foreach ($events as &$event) {
            $event['starts_label'] = $event['starts_at'] . ' UTC';
        }
        unset($event);

        View::render('pages/memory-lane', [
            'pageTitle' => 'Memory lane — AlumniConnect',
            'currentUser' => $user,
            'events' => $events,
            'communityByEvent' => $this->memories->communityForEvents(
                array_column($events, 'id'),
                (int) $user['id'],
                $isAdmin
            ),
        ]);
    }

    public function addDiscussion(): void
    {
        $user = $this->requireUser();
        $this->requireCsrf();
        $eventId = $this->positiveId($_POST['event_id'] ?? null, 'event');
        $body = $_POST['body'] ?? null;
        if (!is_string($body)) {
            $this->invalidInput('Comments must be plain text.');
        }
        $body = trim($body);
        $length = preg_match_all('/./us', $body);
        if ($length === false || $length < 1 || $length > 2000) {
            $this->invalidInput('Comments must be between 1 and 2,000 characters.');
        }

        try {
            $this->memories->addDiscussion($eventId, (int) $user['id'], $body);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        View::redirect($this->returnToEvent($eventId, $_POST['source'] ?? null));
    }

    public function deleteDiscussion(): void
    {
        $user = $this->requireUser();
        $this->requireCsrf();
        $eventId = $this->positiveId($_POST['event_id'] ?? null, 'event');
        $discussionId = $this->positiveId($_POST['discussion_id'] ?? null, 'comment');

        try {
            $this->memories->deleteDiscussion(
                $discussionId,
                (int) $user['id'],
                Auth::hasRole($user, 'admin')
            );
            Session::flash('success', 'The comment was removed.');
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        View::redirect($this->returnToEvent($eventId, $_POST['source'] ?? null));
    }

    public function uploadPhoto(): void
    {
        $user = $this->requireUser();
        $this->requireCsrf();
        $eventId = $this->positiveId($_POST['event_id'] ?? null, 'event');
        $caption = $_POST['caption'] ?? '';
        if (!is_string($caption)) {
            $this->invalidInput('Photo captions must be plain text.');
        }
        $caption = trim($caption);
        $captionLength = preg_match_all('/./us', $caption);
        if ($captionLength === false || $captionLength > 300) {
            $this->invalidInput('Photo captions cannot exceed 300 characters.');
        }

        try {
            $this->uploads->upload($eventId, (int) $user['id'], $_FILES['photo'] ?? null, $caption);
            Session::flash('success', 'Your photo is uploaded and will appear to members after moderator approval.');
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        View::redirect($this->returnToEvent($eventId, $_POST['source'] ?? null));
    }

    public function deletePhoto(): void
    {
        $user = $this->requireUser();
        $this->requireCsrf();
        $eventId = $this->positiveId($_POST['event_id'] ?? null, 'event');
        $photoId = $this->positiveId($_POST['photo_id'] ?? null, 'photo');

        try {
            $this->uploads->delete($photoId, (int) $user['id'], Auth::hasRole($user, 'admin'));
            Session::flash('success', 'The photo was removed.');
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        View::redirect($this->returnToEvent($eventId, $_POST['source'] ?? null));
    }

    public function photo(): void
    {
        $user = $this->requireUser();
        $photoId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($photoId)) {
            View::error(404, 'Photo not found', 'That photo is not available.');
            return;
        }

        $photo = $this->memories->photoForViewer(
            $photoId,
            (int) $user['id'],
            Auth::hasRole($user, 'admin')
        );
        if ($photo === null) {
            View::error(404, 'Photo not found', 'That photo is not available.');
            return;
        }

        $path = $this->uploads->pathForKey($photo['storage_key']);
        if (is_link(dirname($path)) || is_link($path) || !is_file($path)) {
            View::error(404, 'Photo not found', 'That photo is not available.');
            return;
        }
        $byteSize = filesize($path);
        if (!is_int($byteSize) || $byteSize !== (int) $photo['byte_size']) {
            error_log('[application] A private memory photo failed its stored-size check.');
            View::error(404, 'Photo not found', 'That photo is not available.');
            return;
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The private photo could not be opened.');
        }

        header('Content-Type: ' . $photo['mime_type']);
        header('Content-Length: ' . (string) $byteSize);
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        fpassthru($handle);
        fclose($handle);
        exit;
    }

    private function requireUser(): array
    {
        $user = Auth::user();
        if ($user === null) {
            Session::flash('error', 'Sign in to access event discussions and memories.');
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

    private function returnToEvent(int $eventId, mixed $source): string
    {
        if ($source === 'memory-lane') {
            return '/memory-lane#memory-event-' . $eventId;
        }

        return '/events#event-' . $eventId;
    }

    private function invalidInput(string $message): never
    {
        View::error(422, 'Invalid submission', $message);
        exit;
    }
}
