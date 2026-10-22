<?php

declare(strict_types=1);

namespace AlumniConnect\Repositories;

use AlumniConnect\Database\Connection;
use InvalidArgumentException;
use PDO;
use Throwable;

final class CommunityRepository
{
    public function isVerifiedAlumnus(int $userId): bool
    {
        $statement = Connection::get()->prepare(
            'SELECT 1
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id AND r.slug = \'alumni\'
             INNER JOIN alumni_profiles ap ON ap.user_id = ur.user_id AND ap.verification_status = \'verified\'
             INNER JOIN users u ON u.id = ur.user_id AND u.status = \'active\'
             WHERE ur.user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    public function saveMentorProfile(int $userId, string $topics, bool $isAvailable): void
    {
        if (!$this->isVerifiedAlumnus($userId)) {
            throw new InvalidArgumentException('Only alumni verified by an administrator can offer mentorship.');
        }

        $statement = Connection::get()->prepare(
            'INSERT INTO mentorship_profiles (user_id, topics, is_available, created_at, updated_at)
             VALUES (:user_id, :topics, :is_available, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                topics = VALUES(topics),
                is_available = VALUES(is_available),
                updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'user_id' => $userId,
            'topics' => $topics,
            'is_available' => $isAvailable ? 1 : 0,
        ]);
    }

    public function mentorProfile(int $userId): array
    {
        $statement = Connection::get()->prepare(
            'SELECT topics, is_available
             FROM mentorship_profiles
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $profile = $statement->fetch();

        return is_array($profile)
            ? $profile
            : ['topics' => '', 'is_available' => 0];
    }

    public function mentors(int $currentUserId): array
    {
        $statement = Connection::get()->prepare(
            'SELECT
                u.id,
                u.full_name,
                CASE WHEN mp.contact_sharing = 1 THEN u.email ELSE NULL END AS contact_email,
                mp.headline,
                mp.company,
                mp.job_title,
                d.name AS department_name,
                gb.graduation_year,
                mentor_profile.topics,
                mine.status AS request_status,
                CASE
                    WHEN current_profile.batch_id IS NOT NULL AND mp.batch_id = current_profile.batch_id
                        THEN \'Same department and graduating year\'
                    WHEN current_profile.department_id IS NOT NULL AND mp.department_id = current_profile.department_id
                        THEN \'Same department\'
                    ELSE \'No shared department or graduating year specified\'
                END AS match_reason
             FROM mentorship_profiles mentor_profile
             INNER JOIN users u ON u.id = mentor_profile.user_id AND u.status = \'active\'
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id AND r.slug = \'alumni\'
             INNER JOIN alumni_profiles ap ON ap.user_id = u.id AND ap.verification_status = \'verified\'
             INNER JOIN member_profiles mp ON mp.user_id = u.id AND mp.profile_visibility IN (\'members\', \'public\')
             LEFT JOIN departments d ON d.id = mp.department_id
             LEFT JOIN graduation_batches gb ON gb.id = mp.batch_id
             LEFT JOIN member_profiles current_profile ON current_profile.user_id = :current_user_id
             LEFT JOIN mentorship_requests mine
                ON mine.requester_user_id = :requester_user_id AND mine.mentor_user_id = u.id
             WHERE mentor_profile.is_available = 1 AND u.id <> :exclude_user_id
             ORDER BY
                CASE
                    WHEN current_profile.batch_id IS NOT NULL AND mp.batch_id = current_profile.batch_id THEN 0
                    WHEN current_profile.department_id IS NOT NULL AND mp.department_id = current_profile.department_id THEN 1
                    ELSE 2
                END,
                u.full_name'
        );
        $statement->execute([
            'current_user_id' => $currentUserId,
            'requester_user_id' => $currentUserId,
            'exclude_user_id' => $currentUserId,
        ]);

        return $statement->fetchAll();
    }

    public function requestMentorship(int $requesterId, int $mentorId, string $goals): void
    {
        if ($requesterId === $mentorId) {
            throw new InvalidArgumentException('You cannot request mentorship from your own account.');
        }

        $pdo = Connection::get();
        $requester = $pdo->prepare(
            'SELECT 1
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id AND r.slug = \'student\'
             WHERE u.id = :user_id AND u.status = \'active\'
             LIMIT 1'
        );
        $requester->execute(['user_id' => $requesterId]);
        if ($requester->fetchColumn() === false) {
            throw new InvalidArgumentException('Mentorship requests are currently available to active student accounts.');
        }

        $pdo->beginTransaction();
        try {
            $mentor = $pdo->prepare(
                'SELECT profile.user_id
                 FROM mentorship_profiles profile
                 INNER JOIN users u ON u.id = profile.user_id AND u.status = \'active\'
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 INNER JOIN roles r ON r.id = ur.role_id AND r.slug = \'alumni\'
                 INNER JOIN alumni_profiles ap ON ap.user_id = u.id AND ap.verification_status = \'verified\'
                 WHERE profile.user_id = :mentor_id AND profile.is_available = 1
                 FOR UPDATE'
            );
            $mentor->execute(['mentor_id' => $mentorId]);
            if ($mentor->fetchColumn() === false) {
                throw new InvalidArgumentException('That mentor is no longer available for requests.');
            }

            $existing = $pdo->prepare(
                'SELECT id, status
                 FROM mentorship_requests
                 WHERE requester_user_id = :requester_id AND mentor_user_id = :mentor_id
                 FOR UPDATE'
            );
            $existing->execute([
                'requester_id' => $requesterId,
                'mentor_id' => $mentorId,
            ]);
            $request = $existing->fetch();
            if (is_array($request) && in_array($request['status'], ['pending', 'accepted'], true)) {
                throw new InvalidArgumentException('You already have an active mentorship request with this member.');
            }

            if (is_array($request)) {
                $save = $pdo->prepare(
                    'UPDATE mentorship_requests
                     SET goals = :goals, status = \'pending\', updated_at = UTC_TIMESTAMP()
                     WHERE id = :request_id'
                );
                $save->execute([
                    'goals' => $goals,
                    'request_id' => $request['id'],
                ]);
            } else {
                $save = $pdo->prepare(
                    'INSERT INTO mentorship_requests (
                        requester_user_id, mentor_user_id, goals, status, created_at, updated_at
                     ) VALUES (:requester_id, :mentor_id, :goals, \'pending\', UTC_TIMESTAMP(), UTC_TIMESTAMP())'
                );
                $save->execute([
                    'requester_id' => $requesterId,
                    'mentor_id' => $mentorId,
                    'goals' => $goals,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function mentorshipRequests(int $userId): array
    {
        $statement = Connection::get()->prepare(
            'SELECT
                requests.id,
                requests.requester_user_id,
                requests.mentor_user_id,
                requests.goals,
                requests.status,
                requests.created_at,
                requester.full_name AS requester_name,
                mentor.full_name AS mentor_name,
                CASE WHEN requests.mentor_user_id = :mentor_viewer THEN 1 ELSE 0 END AS can_decide
             FROM mentorship_requests requests
             INNER JOIN users requester ON requester.id = requests.requester_user_id
             INNER JOIN users mentor ON mentor.id = requests.mentor_user_id
             WHERE requests.requester_user_id = :requester_viewer
                OR requests.mentor_user_id = :mentor_filter
             ORDER BY requests.updated_at DESC, requests.id DESC'
        );
        $statement->execute([
            'mentor_viewer' => $userId,
            'requester_viewer' => $userId,
            'mentor_filter' => $userId,
        ]);

        return $statement->fetchAll();
    }

    public function decideMentorshipRequest(int $mentorId, int $requestId, string $decision): void
    {
        if (!in_array($decision, ['accepted', 'declined'], true)) {
            throw new InvalidArgumentException('Choose a valid mentorship request decision.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $request = $pdo->prepare(
                'SELECT id, status
                 FROM mentorship_requests
                 WHERE id = :request_id AND mentor_user_id = :mentor_id
                 FOR UPDATE'
            );
            $request->execute([
                'request_id' => $requestId,
                'mentor_id' => $mentorId,
            ]);
            $existing = $request->fetch();
            if (!is_array($existing) || $existing['status'] !== 'pending') {
                throw new InvalidArgumentException('That request is not pending or is not assigned to your account.');
            }

            $update = $pdo->prepare(
                'UPDATE mentorship_requests
                 SET status = :decision, updated_at = UTC_TIMESTAMP()
                 WHERE id = :request_id AND mentor_user_id = :mentor_id'
            );
            $update->execute([
                'decision' => $decision,
                'request_id' => $requestId,
                'mentor_id' => $mentorId,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function canPublishOpportunities(int $userId): bool
    {
        $statement = Connection::get()->prepare(
            'SELECT 1
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             LEFT JOIN alumni_profiles ap ON ap.user_id = u.id
             WHERE u.id = :user_id AND u.status = \'active\'
               AND (r.slug IN (\'organizer\', \'admin\') OR (r.slug = \'alumni\' AND ap.verification_status = \'verified\'))
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    public function publishOpportunity(int $authorId, array $opportunity): int
    {
        if (!$this->canPublishOpportunities($authorId)) {
            throw new InvalidArgumentException('Only verified alumni, organizers, and administrators can publish opportunities.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO opportunities (
                    author_user_id, title, opportunity_type, description, location, expires_at, status
                 ) VALUES (
                    :author_id, :title, :type, :description, :location, :expires_at, \'open\'
                 )'
            );
            $statement->execute([
                'author_id' => $authorId,
                'title' => $opportunity['title'],
                'type' => $opportunity['type'],
                'description' => $opportunity['description'],
                'location' => $opportunity['location'],
                'expires_at' => $opportunity['expires_at'],
            ]);
            $opportunityId = (int) $pdo->lastInsertId();

            $audit = $pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, target_type, target_id, metadata)
                 VALUES (:author_id, \'opportunity.published\', \'opportunity\', :opportunity_id, JSON_OBJECT(\'type\', :type))'
            );
            $audit->execute([
                'author_id' => $authorId,
                'opportunity_id' => $opportunityId,
                'type' => $opportunity['type'],
            ]);
            $pdo->commit();

            return $opportunityId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function opportunities(int $viewerId, bool $isAdmin): array
    {
        $statement = Connection::get()->prepare(
            'SELECT
                opportunities.id,
                opportunities.author_user_id,
                opportunities.title,
                opportunities.opportunity_type,
                opportunities.description,
                opportunities.location,
                opportunities.expires_at,
                opportunities.created_at,
                author.full_name AS author_name,
                CASE WHEN opportunities.author_user_id = :viewer_owner OR :viewer_admin = 1 THEN 1 ELSE 0 END AS can_close
             FROM opportunities
             INNER JOIN users author ON author.id = opportunities.author_user_id AND author.status = \'active\'
             WHERE opportunities.status = \'open\'
               AND (opportunities.expires_at IS NULL OR opportunities.expires_at > UTC_TIMESTAMP())
             ORDER BY opportunities.created_at DESC, opportunities.id DESC'
        );
        $statement->execute([
            'viewer_owner' => $viewerId,
            'viewer_admin' => $isAdmin ? 1 : 0,
        ]);

        return $statement->fetchAll();
    }

    public function closeOpportunity(int $userId, int $opportunityId, bool $isAdmin): void
    {
        $statement = Connection::get()->prepare(
            'UPDATE opportunities
             SET status = \'closed\', updated_at = UTC_TIMESTAMP()
             WHERE id = :opportunity_id AND status = \'open\'
               AND (author_user_id = :author_id OR :is_admin = 1)'
        );
        $statement->execute([
            'opportunity_id' => $opportunityId,
            'author_id' => $userId,
            'is_admin' => $isAdmin ? 1 : 0,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = Connection::get()->prepare(
                'SELECT 1 FROM opportunities WHERE id = :opportunity_id AND status = \'open\''
            );
            $exists->execute(['opportunity_id' => $opportunityId]);
            if ($exists->fetchColumn() !== false) {
                throw new InvalidArgumentException('Only the publisher or an administrator can close this opportunity.');
            }
        }
    }

    public function eventPreferences(int $userId): array
    {
        $statement = Connection::get()->prepare(
            'SELECT preferred_location, available_from, available_until
             FROM event_preferences
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $preferences = $statement->fetch();

        if (!is_array($preferences)) {
            return ['preferred_location' => '', 'available_from' => '', 'available_until' => ''];
        }

        foreach (['preferred_location', 'available_from', 'available_until'] as $key) {
            $preferences[$key] ??= '';
        }

        return $preferences;
    }

    public function saveEventPreferences(int $userId, array $preferences): void
    {
        $statement = Connection::get()->prepare(
            'INSERT INTO event_preferences (
                user_id, preferred_location, available_from, available_until, updated_at
             ) VALUES (
                :user_id, :preferred_location, :available_from, :available_until, UTC_TIMESTAMP()
             )
             ON DUPLICATE KEY UPDATE
                preferred_location = VALUES(preferred_location),
                available_from = VALUES(available_from),
                available_until = VALUES(available_until),
                updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'user_id' => $userId,
            'preferred_location' => $preferences['preferred_location'],
            'available_from' => $preferences['available_from'],
            'available_until' => $preferences['available_until'],
        ]);
    }

    public function matchingEvents(int $userId): array
    {
        $preferences = $this->eventPreferences($userId);
        $where = ['events.status = \'published\'', 'events.starts_at > UTC_TIMESTAMP()'];
        $parameters = [];
        if ($preferences['available_from'] !== null && $preferences['available_from'] !== '') {
            $where[] = 'DATE(events.starts_at) >= :available_from';
            $parameters['available_from'] = $preferences['available_from'];
        }
        if ($preferences['available_until'] !== null && $preferences['available_until'] !== '') {
            $where[] = 'DATE(events.starts_at) <= :available_until';
            $parameters['available_until'] = $preferences['available_until'];
        }
        if ($preferences['preferred_location'] !== null && trim($preferences['preferred_location']) !== '') {
            $location = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($preferences['preferred_location']));
            $where[] = 'events.venue LIKE :location ESCAPE \'!\'';
            $parameters['location'] = '%' . $location . '%';
        }

        $statement = Connection::get()->prepare(
            'SELECT events.id, events.title, events.venue, events.starts_at, events.ends_at,
                    organizer.full_name AS organizer_name,
                    CASE WHEN :reason_location IS NOT NULL AND events.venue LIKE :reason_venue ESCAPE \'!\'
                         THEN 1 ELSE 0 END AS location_match,
                    CASE WHEN (:reason_from IS NOT NULL OR :reason_until IS NOT NULL)
                         AND DATE(events.starts_at) >= :reason_date_from
                         AND DATE(events.starts_at) <= :reason_date_until
                         THEN 1 ELSE 0 END AS date_match
             FROM events
             INNER JOIN users organizer ON organizer.id = events.organizer_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY events.starts_at, events.id
             LIMIT 50'
        );
        $statement->bindValue(
            ':reason_location',
            $preferences['preferred_location'] === '' ? null : $preferences['preferred_location'],
            $preferences['preferred_location'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $locationPattern = '%' . str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            (string) $preferences['preferred_location']
        ) . '%';
        $statement->bindValue(':reason_venue', $locationPattern, PDO::PARAM_STR);
        $statement->bindValue(
            ':reason_from',
            $preferences['available_from'] === '' ? null : $preferences['available_from'],
            $preferences['available_from'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $statement->bindValue(
            ':reason_date_from',
            $preferences['available_from'] === '' ? '1000-01-01' : $preferences['available_from'],
            PDO::PARAM_STR
        );
        $statement->bindValue(
            ':reason_until',
            $preferences['available_until'] === '' ? null : $preferences['available_until'],
            $preferences['available_until'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $statement->bindValue(
            ':reason_date_until',
            $preferences['available_until'] === '' ? '9999-12-31' : $preferences['available_until'],
            PDO::PARAM_STR
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $statement->execute();
        $events = $statement->fetchAll();
        foreach ($events as &$event) {
            $reasons = [];
            if ((int) $event['location_match'] === 1) {
                $reasons[] = 'Venue matches your location preference';
            }
            if ((int) $event['date_match'] === 1) {
                $reasons[] = 'Date fits your availability';
            }
            $event['match_reason'] = $reasons === []
                ? 'No filters saved; showing all upcoming events'
                : implode(' · ', $reasons);
        }
        unset($event);

        return $events;
    }
}
