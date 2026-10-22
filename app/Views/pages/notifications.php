<section class="community-section section-pad" aria-labelledby="notifications-title">
    <div class="community-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Stay in the loop</p>
        <h1 id="notifications-title">Your<br><em>notifications.</em></h1>
        <p>Updates about event discussions and mentorship activity on your account.</p>
    </div>
    <?php $hasUnread = false; ?>
    <?php foreach ($notifications as $notification): ?>
        <?php if ($notification['read_at'] === null) { $hasUnread = true; } ?>
    <?php endforeach; ?>
    <?php if ($hasUnread): ?>
        <form class="notification-read-form" action="/notifications/read" method="post">
            <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
            <button class="event-rsvp-button" type="submit">Mark all as read</button>
        </form>
    <?php endif; ?>

    <?php if ($notifications === []): ?>
        <div class="community-empty">
            <h3>You’re all caught up.</h3>
            <p>New event and mentorship activity will appear here.</p>
        </div>
    <?php else: ?>
        <ol class="notification-list">
            <?php foreach ($notifications as $notification): ?>
                <?php
                $actor = $notification['actor_name'] ?? 'A member';
                $message = match ($notification['notification_type']) {
                    'event_discussion' => $actor . ' commented on ' . ($notification['event_title'] ?? 'an event') . '.',
                    'mentorship_request' => $actor . ' sent you a mentorship request.',
                    'mentorship_accepted' => $actor . ' accepted your mentorship request.',
                    'mentorship_declined' => $actor . ' declined your mentorship request.',
                    'photo_approved' => 'Your photo for ' . ($notification['event_title'] ?? 'an event') . ' was approved.',
                    'photo_rejected' => 'Your photo for ' . ($notification['event_title'] ?? 'an event') . ' was not approved.',
                    'alumni_verified' => 'An administrator verified your alumni status.',
                    'alumni_rejected' => 'An administrator could not verify your alumni status.',
                    default => 'There is new activity on your account.',
                };
                $isPastEvent = $notification['resource_type'] === 'event'
                    && isset($notification['event_starts_at'])
                    && $notification['event_starts_at'] <= gmdate('Y-m-d H:i:s');
                $target = match ($notification['resource_type']) {
                    'event' => ($isPastEvent ? '/memory-lane#memory-event-' : '/events#event-')
                        . (int) $notification['resource_id'],
                    'alumni' => '/profile',
                    default => '/community#requests',
                };
                ?>
                <li class="notification-item <?= $notification['read_at'] === null ? 'notification-unread' : '' ?>">
                    <div>
                        <p><?= \AlumniConnect\Support\View::escape($message) ?></p>
                        <time><?= \AlumniConnect\Support\View::escape($notification['created_at']) ?> UTC</time>
                    </div>
                    <a class="text-link" href="<?= \AlumniConnect\Support\View::escape($target) ?>">Open <span aria-hidden="true">↗</span></a>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
