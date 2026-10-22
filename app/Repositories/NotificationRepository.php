<?php

declare(strict_types=1);

namespace AlumniConnect\Repositories;

use AlumniConnect\Database\Connection;
use InvalidArgumentException;
use PDO;

final class NotificationRepository
{
    public static function create(
        PDO $pdo,
        int $recipientId,
        ?int $actorId,
        string $type,
        string $resourceType,
        int $resourceId
    ): void {
        if ($recipientId < 1 || $resourceId < 1
            || !in_array($type, [
                'event_discussion',
                'mentorship_request',
                'mentorship_accepted',
                'mentorship_declined',
                'photo_approved',
                'photo_rejected',
                'alumni_verified',
                'alumni_rejected',
            ], true)
            || !in_array($resourceType, ['event', 'mentorship', 'alumni'], true)
        ) {
            throw new InvalidArgumentException('The notification details are not valid.');
        }

        $statement = $pdo->prepare(
            'INSERT INTO notifications (
                recipient_user_id, actor_user_id, notification_type, resource_type, resource_id, created_at
             ) VALUES (
                :recipient_id, :actor_id, :notification_type, :resource_type, :resource_id, UTC_TIMESTAMP()
             )'
        );
        $statement->execute([
            'recipient_id' => $recipientId,
            'actor_id' => $actorId,
            'notification_type' => $type,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);
    }

    public function forUser(int $userId): array
    {
        $statement = Connection::get()->prepare(
            'SELECT
                notifications.id,
                notifications.notification_type,
                notifications.resource_type,
                notifications.resource_id,
                notifications.created_at,
                notifications.read_at,
                actor.full_name AS actor_name,
                event.title AS event_title,
                event.ends_at AS event_ends_at,
                event.starts_at AS event_starts_at
             FROM notifications
             LEFT JOIN users actor ON actor.id = notifications.actor_user_id
             LEFT JOIN events event
                ON notifications.resource_type = \'event\' AND event.id = notifications.resource_id
             WHERE notifications.recipient_user_id = :user_id
             ORDER BY notifications.created_at DESC, notifications.id DESC
             LIMIT 50'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function markAllRead(int $userId): void
    {
        $statement = Connection::get()->prepare(
            'UPDATE notifications
             SET read_at = UTC_TIMESTAMP()
             WHERE recipient_user_id = :user_id AND read_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);
    }
}
