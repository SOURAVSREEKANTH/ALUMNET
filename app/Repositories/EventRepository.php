<?php

declare(strict_types=1);

namespace AlumniConnect\Repositories;

use AlumniConnect\Database\Connection;
use InvalidArgumentException;
use PDO;
use Throwable;

final class EventRepository
{
    public function upcomingForUser(int $userId, bool $canManageAll = false): array
    {
        $statement = Connection::get()->prepare(
            'SELECT
                e.id,
                e.organizer_id,
                e.title,
                e.description,
                e.venue,
                e.starts_at,
                e.ends_at,
                e.capacity,
                e.status,
                organizer.full_name AS organizer_name,
                CASE WHEN e.organizer_id = :manager_user OR :manager_admin = 1 THEN 1 ELSE 0 END AS can_manage,
                (SELECT COUNT(*)
                 FROM event_rsvps attending
                 WHERE attending.event_id = e.id AND attending.state = \'attending\') AS attendee_count,
                (SELECT COUNT(*)
                 FROM event_waitlist waiting
                 INNER JOIN users waiting_member ON waiting_member.id = waiting.user_id
                     AND waiting_member.status = \'active\'
                 WHERE waiting.event_id = e.id AND waiting.status = \'waiting\') AS waitlist_count,
                CASE
                    WHEN mine.state = \'attending\' THEN \'attending\'
                    WHEN my_waitlist.status = \'waiting\' THEN \'waitlisted\'
                    WHEN mine.state = \'interested\' THEN \'interested\'
                    WHEN mine.state = \'declined\' THEN \'declined\'
                    ELSE NULL
                END AS my_state,
                CASE WHEN my_waitlist.status = \'waiting\' THEN (
                    SELECT COUNT(*) + 1
                    FROM event_waitlist earlier
                    INNER JOIN users earlier_member ON earlier_member.id = earlier.user_id
                    WHERE earlier.event_id = e.id
                      AND earlier.status = \'waiting\'
                      AND earlier_member.status = \'active\'
                      AND (
                          earlier.created_at < my_waitlist.created_at
                          OR (earlier.created_at = my_waitlist.created_at AND earlier.id < my_waitlist.id)
                      )
                ) ELSE NULL END AS my_waitlist_position
             FROM events e
             INNER JOIN users organizer ON organizer.id = e.organizer_id
             LEFT JOIN event_rsvps mine ON mine.event_id = e.id AND mine.user_id = :viewer_rsvp
             LEFT JOIN event_waitlist my_waitlist ON my_waitlist.event_id = e.id
                 AND my_waitlist.user_id = :viewer_waitlist
             WHERE e.status = \'published\' AND e.starts_at > UTC_TIMESTAMP()
             ORDER BY e.starts_at, e.id'
        );
        $statement->execute([
            'viewer_rsvp' => $userId,
            'viewer_waitlist' => $userId,
            'manager_user' => $userId,
            'manager_admin' => $canManageAll ? 1 : 0,
        ]);

        return $statement->fetchAll();
    }

    public function publish(int $organizerId, array $event): int
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO events (
                    organizer_id, title, description, venue, starts_at, ends_at, capacity, status
                 ) VALUES (
                    :organizer_id, :title, :description, :venue, :starts_at, :ends_at, :capacity, \'published\'
                 )'
            );
            $statement->bindValue(':organizer_id', $organizerId, PDO::PARAM_INT);
            $statement->bindValue(':title', $event['title'], PDO::PARAM_STR);
            $statement->bindValue(':description', $event['description'], PDO::PARAM_STR);
            $statement->bindValue(':venue', $event['venue'], PDO::PARAM_STR);
            $statement->bindValue(':starts_at', $event['starts_at'], PDO::PARAM_STR);
            $statement->bindValue(':ends_at', $event['ends_at'], PDO::PARAM_STR);
            $statement->bindValue(
                ':capacity',
                $event['capacity'],
                $event['capacity'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT
            );
            $statement->execute();
            $eventId = (int) $pdo->lastInsertId();

            $audit = $pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, target_type, target_id, metadata)
                 VALUES (:actor_id, \'event.published\', \'event\', :event_id, JSON_OBJECT(\'capacity\', :capacity))'
            );
            $audit->bindValue(':actor_id', $organizerId, PDO::PARAM_INT);
            $audit->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $audit->bindValue(
                ':capacity',
                $event['capacity'],
                $event['capacity'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT
            );
            $audit->execute();
            $pdo->commit();

            return $eventId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

            public function issuePass(int $eventId, int $userId): array
            {
                $pdo = Connection::get();
                $pdo->beginTransaction();
                try {
                    $eventQuery = $pdo->prepare(
                        'SELECT events.id, events.title, events.starts_at, events.ends_at, events.venue
                         FROM events
                         INNER JOIN event_rsvps ON event_rsvps.event_id = events.id
                             AND event_rsvps.user_id = :rsvp_user
                             AND event_rsvps.state = \'attending\'
                         WHERE events.id = :event_id
                           AND events.status = \'published\'
                           AND events.starts_at > UTC_TIMESTAMP()
                         FOR UPDATE'
                    );
                    $eventQuery->execute([
                        'rsvp_user' => $userId,
                        'event_id' => $eventId,
                    ]);
                    $event = $eventQuery->fetch();
                    if (!is_array($event)) {
                        throw new InvalidArgumentException('A pass is available only for an upcoming event where your place is confirmed.');
                    }

                    $existingCheckIn = $pdo->prepare(
                        'SELECT 1 FROM event_checkins WHERE event_id = :event_id AND user_id = :user_id'
                    );
                    $existingCheckIn->execute([
                        'event_id' => $eventId,
                        'user_id' => $userId,
                    ]);
                    if ($existingCheckIn->fetchColumn() !== false) {
                        throw new InvalidArgumentException('You have already checked in to this event.');
                    }

                    $token = bin2hex(random_bytes(32));
                    $pass = $pdo->prepare(
                        'INSERT INTO event_passes (event_id, user_id, token_hash, issued_at, expires_at, revoked_at)
                         VALUES (:event_id, :user_id, :token_hash, UTC_TIMESTAMP(), :expires_at, NULL)
                         ON DUPLICATE KEY UPDATE
                            token_hash = VALUES(token_hash),
                            issued_at = UTC_TIMESTAMP(),
                            expires_at = VALUES(expires_at),
                            revoked_at = NULL'
                    );
                    $pass->execute([
                        'event_id' => $eventId,
                        'user_id' => $userId,
                        'token_hash' => hash('sha256', $token),
                        'expires_at' => $event['ends_at'],
                    ]);

                    $pdo->commit();
                    return [
                        'token' => $token,
                        'event' => $event,
                    ];
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    throw $exception;
                }
            }

            public function findManagedEvent(int $eventId, int $userId, bool $isAdmin): ?array
            {
                $statement = Connection::get()->prepare(
                    'SELECT events.id, events.title, events.venue, events.starts_at, events.ends_at
                     FROM events
                     WHERE events.id = :event_id
                       AND (events.organizer_id = :organizer_id OR :is_admin = 1)
                     LIMIT 1'
                );
                $statement->execute([
                    'event_id' => $eventId,
                    'organizer_id' => $userId,
                    'is_admin' => $isAdmin ? 1 : 0,
                ]);
                $event = $statement->fetch();

                return is_array($event) ? $event : null;
            }

            public function checkIn(int $eventId, int $operatorId, bool $isAdmin, string $token, string $method): string
            {
                if (!in_array($method, ['qr', 'manual'], true)) {
                    throw new InvalidArgumentException('Choose a valid check-in method.');
                }
                if (preg_match('/\A[a-f0-9]{64}\z/i', $token) !== 1) {
                    throw new InvalidArgumentException('Enter a valid 64-character event pass code.');
                }

                $pdo = Connection::get();
                $pdo->beginTransaction();
                try {
                    $eventQuery = $pdo->prepare(
                        'SELECT id, title, status, starts_at, ends_at
                         FROM events
                         WHERE id = :event_id
                           AND (organizer_id = :organizer_id OR :is_admin = 1)
                         FOR UPDATE'
                    );
                    $eventQuery->execute([
                        'event_id' => $eventId,
                        'organizer_id' => $operatorId,
                        'is_admin' => $isAdmin ? 1 : 0,
                    ]);
                    $event = $eventQuery->fetch();
                    if (!is_array($event)) {
                        throw new InvalidArgumentException('That event is not available for your organizer account.');
                    }
                    if (
                        $event['status'] !== 'published'
                        || $event['starts_at'] > gmdate('Y-m-d H:i:s')
                        || $event['ends_at'] <= gmdate('Y-m-d H:i:s')
                    ) {
                        throw new InvalidArgumentException('Check-in is available only while the event is in progress.');
                    }

                    $passQuery = $pdo->prepare(
                        'SELECT passes.id, passes.user_id, member.full_name
                         FROM event_passes passes
                         INNER JOIN users member ON member.id = passes.user_id AND member.status = \'active\'
                         INNER JOIN event_rsvps rsvp ON rsvp.event_id = passes.event_id
                             AND rsvp.user_id = passes.user_id
                             AND rsvp.state = \'attending\'
                         WHERE passes.event_id = :event_id
                           AND passes.token_hash = :token_hash
                           AND passes.revoked_at IS NULL
                           AND passes.expires_at > UTC_TIMESTAMP()
                         FOR UPDATE'
                    );
                    $passQuery->execute([
                        'event_id' => $eventId,
                        'token_hash' => hash('sha256', strtolower($token)),
                    ]);
                    $pass = $passQuery->fetch();
                    if (!is_array($pass)) {
                        throw new InvalidArgumentException('That pass is invalid, expired, revoked, or not eligible for this event.');
                    }

                    $existing = $pdo->prepare(
                        'SELECT 1 FROM event_checkins WHERE event_id = :event_id AND user_id = :user_id'
                    );
                    $existing->execute([
                        'event_id' => $eventId,
                        'user_id' => $pass['user_id'],
                    ]);
                    if ($existing->fetchColumn() !== false) {
                        throw new InvalidArgumentException('This pass has already been checked in.');
                    }

                    $checkIn = $pdo->prepare(
                        'INSERT INTO event_checkins (
                            event_id, user_id, pass_id, checked_in_by, method, checked_in_at
                         ) VALUES (
                            :event_id, :user_id, :pass_id, :operator_id, :method, UTC_TIMESTAMP()
                         )'
                    );
                    $checkIn->execute([
                        'event_id' => $eventId,
                        'user_id' => $pass['user_id'],
                        'pass_id' => $pass['id'],
                        'operator_id' => $operatorId,
                        'method' => $method,
                    ]);

                    $audit = $pdo->prepare(
                        'INSERT INTO audit_logs (actor_user_id, action, target_type, target_id, metadata)
                         VALUES (:operator_id, \'event.checked_in\', \'event\', :event_id, JSON_OBJECT(\'method\', :method))'
                    );
                    $audit->execute([
                        'operator_id' => $operatorId,
                        'event_id' => $eventId,
                        'method' => $method,
                    ]);

                    $pdo->commit();
                    return (string) $pass['full_name'];
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    throw $exception;
                }
            }

            public function attendanceReport(int $eventId, int $userId, bool $isAdmin): ?array
            {
                $statement = Connection::get()->prepare(
                    'SELECT
                        events.id,
                        events.title,
                        events.venue,
                        events.starts_at,
                        events.ends_at,
                        events.capacity,
                        events.status,
                        organizer.full_name AS organizer_name,
                        (SELECT COUNT(*) FROM event_rsvps WHERE event_id = events.id AND state = \'attending\') AS attending_count,
                        (SELECT COUNT(*) FROM event_rsvps WHERE event_id = events.id AND state = \'interested\') AS interested_count,
                        (SELECT COUNT(*) FROM event_rsvps WHERE event_id = events.id AND state = \'waitlisted\') AS waitlisted_rsvp_count,
                        (SELECT COUNT(*) FROM event_waitlist WHERE event_id = events.id AND status = \'waiting\') AS waiting_count,
                        (SELECT COUNT(*) FROM event_checkins WHERE event_id = events.id) AS checked_in_count
                     FROM events
                     INNER JOIN users organizer ON organizer.id = events.organizer_id
                     WHERE events.id = :event_id
                       AND (events.organizer_id = :organizer_id OR :is_admin = 1)
                     LIMIT 1'
                );
                $statement->execute([
                    'event_id' => $eventId,
                    'organizer_id' => $userId,
                    'is_admin' => $isAdmin ? 1 : 0,
                ]);
                $report = $statement->fetch();
                if (!is_array($report)) {
                    return null;
                }

                foreach ([
                    'attending_count',
                    'interested_count',
                    'waitlisted_rsvp_count',
                    'waiting_count',
                    'checked_in_count',
                ] as $count) {
                    $report[$count] = (int) $report[$count];
                }
                $report['capacity'] = $report['capacity'] === null ? null : (int) $report['capacity'];
                $report['conversion_rate'] = $report['attending_count'] === 0
                    ? null
                    : round($report['checked_in_count'] / $report['attending_count'] * 100, 1);

                return $report;
            }

    public function respond(int $eventId, int $userId, string $action): string
    {
        if (!in_array($action, ['attend', 'interested', 'decline'], true)) {
            throw new InvalidArgumentException('Choose a valid RSVP action.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $eventQuery = $pdo->prepare(
                'SELECT id, status, starts_at, capacity
                 FROM events
                 WHERE id = :event_id
                 FOR UPDATE'
            );
            $eventQuery->execute(['event_id' => $eventId]);
            $event = $eventQuery->fetch();
            if (!is_array($event)) {
                throw new InvalidArgumentException('That event is no longer available.');
            }
            if ($event['status'] !== 'published' || $event['starts_at'] <= gmdate('Y-m-d H:i:s')) {
                throw new InvalidArgumentException('RSVPs are closed for this event.');
            }

            $rsvpQuery = $pdo->prepare(
                'SELECT state FROM event_rsvps WHERE event_id = :event_id AND user_id = :user_id FOR UPDATE'
            );
            $rsvpQuery->execute([
                'event_id' => $eventId,
                'user_id' => $userId,
            ]);
            $existingState = $rsvpQuery->fetchColumn();

            $waitlistQuery = $pdo->prepare(
                'SELECT status FROM event_waitlist WHERE event_id = :event_id AND user_id = :user_id FOR UPDATE'
            );
            $waitlistQuery->execute([
                'event_id' => $eventId,
                'user_id' => $userId,
            ]);
            $existingWaitlistStatus = $waitlistQuery->fetchColumn();

            if ($action === 'decline') {
                if (!is_string($existingState)) {
                    throw new InvalidArgumentException('You do not have an RSVP or waitlist place to cancel.');
                }

                $rsvpUpdate = $pdo->prepare(
                    'UPDATE event_rsvps
                     SET state = \'declined\', updated_at = UTC_TIMESTAMP()
                     WHERE event_id = :event_id AND user_id = :user_id'
                );
                $rsvpUpdate->execute(['event_id' => $eventId, 'user_id' => $userId]);
                $waitlistUpdate = $pdo->prepare(
                    'UPDATE event_waitlist
                     SET status = \'cancelled\', updated_at = UTC_TIMESTAMP()
                     WHERE event_id = :event_id AND user_id = :user_id AND status = \'waiting\''
                );
                $waitlistUpdate->execute(['event_id' => $eventId, 'user_id' => $userId]);

                if ($existingState === 'attending') {
                    $this->promoteNextWaitingMember($pdo, $eventId);
                }

                $pdo->commit();
                return 'declined';
            }

            if ($action === 'interested') {
                $rsvpUpdate = $pdo->prepare(
                    'INSERT INTO event_rsvps (event_id, user_id, state, created_at, updated_at)
                     VALUES (:event_id, :user_id, \'interested\', UTC_TIMESTAMP(), UTC_TIMESTAMP())
                     ON DUPLICATE KEY UPDATE state = \'interested\', updated_at = UTC_TIMESTAMP()'
                );
                $rsvpUpdate->execute(['event_id' => $eventId, 'user_id' => $userId]);
                if ($existingWaitlistStatus === 'waiting') {
                    $waitlistUpdate = $pdo->prepare(
                        'UPDATE event_waitlist
                         SET status = \'cancelled\', updated_at = UTC_TIMESTAMP()
                         WHERE event_id = :event_id AND user_id = :user_id AND status = \'waiting\''
                    );
                    $waitlistUpdate->execute(['event_id' => $eventId, 'user_id' => $userId]);
                }
                if ($existingState === 'attending') {
                    $this->promoteNextWaitingMember($pdo, $eventId);
                }

                $pdo->commit();
                return 'interested';
            }

            if ($existingState === 'attending') {
                $pdo->commit();
                return 'attending';
            }
            if ($existingWaitlistStatus === 'waiting') {
                $pdo->commit();
                return 'waitlisted';
            }

            $attendeeCount = $pdo->prepare(
                'SELECT COUNT(*) FROM event_rsvps WHERE event_id = :event_id AND state = \'attending\''
            );
            $attendeeCount->execute(['event_id' => $eventId]);
            $hasSeat = $event['capacity'] === null
                || (int) $attendeeCount->fetchColumn() < (int) $event['capacity'];
            $state = $hasSeat ? 'attending' : 'waitlisted';

            $rsvpUpdate = $pdo->prepare(
                'INSERT INTO event_rsvps (event_id, user_id, state, created_at, updated_at)
                 VALUES (:event_id, :user_id, :state, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE state = VALUES(state), updated_at = UTC_TIMESTAMP()'
            );
            $rsvpUpdate->execute([
                'event_id' => $eventId,
                'user_id' => $userId,
                'state' => $state,
            ]);

            if ($hasSeat && $existingWaitlistStatus === 'cancelled') {
                $cancelledWaitlist = $pdo->prepare(
                    'UPDATE event_waitlist
                     SET status = \'cancelled\', updated_at = UTC_TIMESTAMP()
                     WHERE event_id = :event_id AND user_id = :user_id'
                );
                $cancelledWaitlist->execute(['event_id' => $eventId, 'user_id' => $userId]);
            } elseif (!$hasSeat) {
                $waitlistInsert = $pdo->prepare(
                    'INSERT INTO event_waitlist (event_id, user_id, status, created_at, updated_at)
                     VALUES (:event_id, :user_id, \'waiting\', UTC_TIMESTAMP(), UTC_TIMESTAMP())
                     ON DUPLICATE KEY UPDATE
                        status = \'waiting\',
                        created_at = UTC_TIMESTAMP(),
                        updated_at = UTC_TIMESTAMP()'
                );
                $waitlistInsert->execute(['event_id' => $eventId, 'user_id' => $userId]);
            }

            $pdo->commit();
            return $state;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function promoteNextWaitingMember(PDO $pdo, int $eventId): void
    {
        $nextMember = $pdo->prepare(
            'SELECT waitlist.user_id
             FROM event_waitlist waitlist
             INNER JOIN event_rsvps rsvp
                 ON rsvp.event_id = waitlist.event_id AND rsvp.user_id = waitlist.user_id
             INNER JOIN users member ON member.id = waitlist.user_id
             WHERE waitlist.event_id = :event_id
               AND waitlist.status = \'waiting\'
               AND rsvp.state = \'waitlisted\'
               AND member.status = \'active\'
             ORDER BY waitlist.created_at, waitlist.id
             LIMIT 1
             FOR UPDATE'
        );
        $nextMember->execute(['event_id' => $eventId]);
        $userId = $nextMember->fetchColumn();
        if ($userId === false) {
            return;
        }

        $rsvpPromotion = $pdo->prepare(
            'UPDATE event_rsvps
             SET state = \'attending\', updated_at = UTC_TIMESTAMP()
             WHERE event_id = :event_id AND user_id = :user_id AND state = \'waitlisted\''
        );
        $rsvpPromotion->execute(['event_id' => $eventId, 'user_id' => $userId]);
        if ($rsvpPromotion->rowCount() !== 1) {
            return;
        }

        $waitlistPromotion = $pdo->prepare(
            'UPDATE event_waitlist
             SET status = \'promoted\', updated_at = UTC_TIMESTAMP()
             WHERE event_id = :event_id AND user_id = :user_id AND status = \'waiting\''
        );
        $waitlistPromotion->execute(['event_id' => $eventId, 'user_id' => $userId]);
    }
}
