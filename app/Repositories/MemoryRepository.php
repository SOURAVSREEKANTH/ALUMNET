<?php

declare(strict_types=1);

namespace AlumniConnect\Repositories;

use AlumniConnect\Database\Connection;
use InvalidArgumentException;
use PDO;
use Throwable;

final class MemoryRepository
{
    public function pastEvents(): array
    {
        $statement = Connection::get()->query(
            'SELECT
                events.id,
                events.organizer_id,
                events.title,
                events.venue,
                events.starts_at,
                organizer.full_name AS organizer_name
             FROM events
             INNER JOIN users organizer ON organizer.id = events.organizer_id
             WHERE events.status = \'published\' AND events.starts_at <= UTC_TIMESTAMP()
             ORDER BY events.starts_at DESC, events.id DESC
             LIMIT 30'
        );

        return $statement->fetchAll();
    }

    public function communityForEvents(array $eventIds, int $viewerId, bool $isAdmin): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $eventIds),
            static fn (int $id): bool => $id > 0
        )));
        $content = [];
        foreach ($ids as $id) {
            $content[$id] = ['discussions' => [], 'photos' => []];
        }
        if ($ids === []) {
            return $content;
        }

        [$inClause, $parameters] = $this->eventIdParameters($ids);
        $discussionParameters = $parameters + [
            'viewer_author' => $viewerId,
            'viewer_organizer' => $viewerId,
            'is_admin' => $isAdmin ? 1 : 0,
        ];
        $discussions = Connection::get()->prepare(
            'SELECT
                discussion.id,
                discussion.event_id,
                discussion.author_user_id,
                discussion.body,
                discussion.created_at,
                discussion.deleted_at,
                author.full_name AS author_name,
                CASE
                    WHEN discussion.author_user_id = :viewer_author
                      OR event.organizer_id = :viewer_organizer
                      OR :is_admin = 1 THEN 1
                    ELSE 0
                END AS can_delete
             FROM (
                SELECT ranked_discussions.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY ranked_discussions.event_id
                        ORDER BY ranked_discussions.created_at DESC, ranked_discussions.id DESC
                    ) AS item_rank
                FROM event_discussions ranked_discussions
                WHERE ranked_discussions.event_id IN (' . $inClause . ')
             ) discussion
             INNER JOIN users author ON author.id = discussion.author_user_id
             INNER JOIN events event ON event.id = discussion.event_id
             WHERE discussion.item_rank <= 20
             ORDER BY discussion.event_id, discussion.created_at DESC, discussion.id DESC'
        );
        $discussions->execute($discussionParameters);
        foreach ($discussions->fetchAll() as $discussion) {
            $content[(int) $discussion['event_id']]['discussions'][] = $discussion;
        }

        $photos = Connection::get()->prepare(
            'SELECT
                photo.id,
                album.event_id,
                photo.uploader_user_id,
                photo.storage_key,
                photo.caption,
                photo.moderation_status,
                photo.created_at,
                uploader.full_name AS uploader_name,
                CASE
                    WHEN photo.uploader_user_id = :viewer_uploader
                      OR event.organizer_id = :viewer_organizer
                      OR :is_admin = 1 THEN 1
                    ELSE 0
                END AS can_delete
             FROM (
                SELECT ranked_photos.*, ranked_album.event_id,
                    ROW_NUMBER() OVER (
                        PARTITION BY ranked_album.event_id
                        ORDER BY ranked_photos.created_at DESC, ranked_photos.id DESC
                    ) AS item_rank
                FROM event_photos ranked_photos
                INNER JOIN photo_albums ranked_album ON ranked_album.id = ranked_photos.album_id
                WHERE ranked_album.event_id IN (' . $inClause . ')
                  AND ranked_photos.deleted_at IS NULL
             ) photo
             INNER JOIN photo_albums album ON album.id = photo.album_id
             INNER JOIN events event ON event.id = album.event_id
             INNER JOIN users uploader ON uploader.id = photo.uploader_user_id
             WHERE photo.item_rank <= 12
               AND (
                    photo.moderation_status = \'approved\'
                    OR photo.uploader_user_id = :viewer_owner
                    OR event.organizer_id = :viewer_event_owner
                    OR :admin_viewer = 1
               )
             ORDER BY photo.created_at DESC, photo.id DESC'
        );
        $photos->execute($parameters + [
            'viewer_uploader' => $viewerId,
            'viewer_organizer' => $viewerId,
            'is_admin' => $isAdmin ? 1 : 0,
            'viewer_owner' => $viewerId,
            'viewer_event_owner' => $viewerId,
            'admin_viewer' => $isAdmin ? 1 : 0,
        ]);
        foreach ($photos->fetchAll() as $photo) {
            $content[(int) $photo['event_id']]['photos'][] = $photo;
        }

        return $content;
    }

    public function addDiscussion(int $eventId, int $authorId, string $body): int
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $event = $pdo->prepare(
                'SELECT organizer_id
                 FROM events
                 WHERE id = :event_id AND status = \'published\'
                 FOR UPDATE'
            );
            $event->execute(['event_id' => $eventId]);
            $organizerId = $event->fetchColumn();
            if ($organizerId === false) {
                throw new InvalidArgumentException('That event is no longer available for discussion.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO event_discussions (event_id, author_user_id, body, created_at)
                 VALUES (:event_id, :author_id, :body, UTC_TIMESTAMP())'
            );
            $insert->execute([
                'event_id' => $eventId,
                'author_id' => $authorId,
                'body' => $body,
            ]);
            $discussionId = (int) $pdo->lastInsertId();
            $organizerId = (int) $organizerId;
            if ($organizerId !== $authorId) {
                NotificationRepository::create(
                    $pdo,
                    $organizerId,
                    $authorId,
                    'event_discussion',
                    'event',
                    $eventId
                );
            }
            $pdo->commit();

            return $discussionId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function deleteDiscussion(int $discussionId, int $actorId, bool $isAdmin): void
    {
        $statement = Connection::get()->prepare(
            'UPDATE event_discussions discussion
             INNER JOIN events event ON event.id = discussion.event_id
             SET discussion.deleted_at = UTC_TIMESTAMP()
             WHERE discussion.id = :discussion_id
               AND discussion.deleted_at IS NULL
               AND (
                    discussion.author_user_id = :author_id
                    OR event.organizer_id = :organizer_id
                    OR :admin_id = 1
               )'
        );
        $statement->execute([
            'discussion_id' => $discussionId,
            'author_id' => $actorId,
            'organizer_id' => $actorId,
            'admin_id' => $isAdmin ? 1 : 0,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('That comment is unavailable or you cannot remove it.');
        }
    }

    public function addPhoto(int $eventId, int $uploaderId, array $photo): int
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $album = $pdo->prepare(
                'INSERT IGNORE INTO photo_albums (event_id, owner_user_id)
                 SELECT id, organizer_id FROM events
                 WHERE id = :event_id AND status = \'published\''
            );
            $album->execute(['event_id' => $eventId]);

            $albumQuery = $pdo->prepare(
                'SELECT album.id
                 FROM photo_albums album
                 INNER JOIN events event ON event.id = album.event_id
                 WHERE album.event_id = :event_id AND event.status = \'published\'
                 FOR UPDATE'
            );
            $albumQuery->execute(['event_id' => $eventId]);
            $albumId = $albumQuery->fetchColumn();
            if ($albumId === false) {
                throw new InvalidArgumentException('That event is no longer available for memory uploads.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO event_photos (
                    album_id, uploader_user_id, storage_key, mime_type, byte_size,
                    width, height, caption, moderation_status, created_at
                 ) VALUES (
                    :album_id, :uploader_id, :storage_key, :mime_type, :byte_size,
                    :width, :height, :caption, \'pending\', UTC_TIMESTAMP()
                 )'
            );
            $insert->execute([
                'album_id' => $albumId,
                'uploader_id' => $uploaderId,
                'storage_key' => $photo['storage_key'],
                'mime_type' => $photo['mime_type'],
                'byte_size' => $photo['byte_size'],
                'width' => $photo['width'],
                'height' => $photo['height'],
                'caption' => $photo['caption'],
            ]);
            $photoId = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $photoId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function photoForViewer(int $photoId, int $viewerId, bool $isAdmin): ?array
    {
        $statement = Connection::get()->prepare(
            'SELECT photo.id, photo.storage_key, photo.mime_type, photo.byte_size
             FROM event_photos photo
             INNER JOIN photo_albums album ON album.id = photo.album_id
             INNER JOIN events event ON event.id = album.event_id
             WHERE photo.id = :photo_id
               AND photo.deleted_at IS NULL
               AND event.status = \'published\'
               AND photo.moderation_status <> \'rejected\'
               AND (
                    photo.moderation_status = \'approved\'
                    OR photo.uploader_user_id = :uploader_id
                    OR event.organizer_id = :organizer_id
                    OR :admin_id = 1
               )'
        );
        $statement->execute([
            'photo_id' => $photoId,
            'uploader_id' => $viewerId,
            'organizer_id' => $viewerId,
            'admin_id' => $isAdmin ? 1 : 0,
        ]);
        $photo = $statement->fetch();

        return is_array($photo) ? $photo : null;
    }

    public function deletePhoto(int $photoId, int $actorId, bool $isAdmin): string
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $photoQuery = $pdo->prepare(
                'SELECT photo.storage_key
                 FROM event_photos photo
                 INNER JOIN photo_albums album ON album.id = photo.album_id
                 INNER JOIN events event ON event.id = album.event_id
                 WHERE photo.id = :photo_id
                   AND photo.deleted_at IS NULL
                   AND (
                        photo.uploader_user_id = :uploader_id
                        OR event.organizer_id = :organizer_id
                        OR :admin_id = 1
                   )
                 FOR UPDATE'
            );
            $photoQuery->execute([
                'photo_id' => $photoId,
                'uploader_id' => $actorId,
                'organizer_id' => $actorId,
                'admin_id' => $isAdmin ? 1 : 0,
            ]);
            $storageKey = $photoQuery->fetchColumn();
            if (!is_string($storageKey)) {
                throw new InvalidArgumentException('That photo is unavailable or you cannot remove it.');
            }

            $delete = $pdo->prepare(
                'UPDATE event_photos SET deleted_at = UTC_TIMESTAMP()
                 WHERE id = :photo_id AND deleted_at IS NULL'
            );
            $delete->execute(['photo_id' => $photoId]);
            $pdo->commit();

            return $storageKey;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function eventIdParameters(array $ids): array
    {
        $names = [];
        $parameters = [];
        foreach ($ids as $index => $id) {
            $name = 'event_' . $index;
            $names[] = ':' . $name;
            $parameters[$name] = $id;
        }

        return [implode(', ', $names), $parameters];
    }
}
