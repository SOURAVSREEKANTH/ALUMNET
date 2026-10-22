<?php

declare(strict_types=1);

$eventId = (int) $event['id'];
$eventCommunity = $communityByEvent[$eventId] ?? ['discussions' => [], 'photos' => []];
$source = $isMemoryLane ? 'memory-lane' : 'events';
?>
<section class="event-community" aria-label="Event discussions and photos">
    <div class="event-community-heading">
        <h3>Keep the conversation going</h3>
        <p>Share a note with members attending or remembering this event.</p>
    </div>
    <form class="event-discussion-form" action="/events/discussion" method="post">
        <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
        <input type="hidden" name="event_id" value="<?= \AlumniConnect\Support\View::escape($eventId) ?>">
        <input type="hidden" name="source" value="<?= \AlumniConnect\Support\View::escape($source) ?>">
        <label for="discussion-<?= \AlumniConnect\Support\View::escape($source) ?>-<?= \AlumniConnect\Support\View::escape($eventId) ?>">Your comment</label>
        <textarea id="discussion-<?= \AlumniConnect\Support\View::escape($source) ?>-<?= \AlumniConnect\Support\View::escape($eventId) ?>" name="body" rows="3" maxlength="2000" required></textarea>
        <button class="event-rsvp-button" type="submit">Post comment</button>
    </form>

    <?php if ($eventCommunity['discussions'] !== []): ?>
        <ul class="event-discussion-list">
            <?php foreach ($eventCommunity['discussions'] as $discussion): ?>
                <li class="event-discussion-item">
                    <div class="event-discussion-meta">
                        <strong><?= \AlumniConnect\Support\View::escape($discussion['author_name']) ?></strong>
                        <time><?= \AlumniConnect\Support\View::escape($discussion['created_at']) ?> UTC</time>
                    </div>
                    <?php if ($discussion['deleted_at'] !== null): ?>
                        <p class="event-removed-note">This comment was removed.</p>
                    <?php else: ?>
                        <p><?= nl2br(\AlumniConnect\Support\View::escape($discussion['body'])) ?></p>
                        <?php if ((bool) $discussion['can_delete']): ?>
                            <form action="/events/discussion/delete" method="post">
                                <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                <input type="hidden" name="event_id" value="<?= \AlumniConnect\Support\View::escape($eventId) ?>">
                                <input type="hidden" name="discussion_id" value="<?= \AlumniConnect\Support\View::escape($discussion['id']) ?>">
                                <input type="hidden" name="source" value="<?= \AlumniConnect\Support\View::escape($source) ?>">
                                <button class="event-remove-button" type="submit">Remove comment</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="event-community-empty">No comments yet. Start the conversation.</p>
    <?php endif; ?>

    <div class="event-memory-heading">
        <h3>Photos and memories</h3>
        <p>JPEG or PNG, up to 2 MB. New uploads stay private to you and moderators until approved.</p>
    </div>
    <form class="event-photo-form" action="/events/photo" method="post" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
        <input type="hidden" name="event_id" value="<?= \AlumniConnect\Support\View::escape($eventId) ?>">
        <input type="hidden" name="source" value="<?= \AlumniConnect\Support\View::escape($source) ?>">
        <label for="photo-<?= \AlumniConnect\Support\View::escape($source) ?>-<?= \AlumniConnect\Support\View::escape($eventId) ?>">Choose a photo</label>
        <input id="photo-<?= \AlumniConnect\Support\View::escape($source) ?>-<?= \AlumniConnect\Support\View::escape($eventId) ?>" name="photo" type="file" accept="image/jpeg,image/png" required>
        <label for="caption-<?= \AlumniConnect\Support\View::escape($source) ?>-<?= \AlumniConnect\Support\View::escape($eventId) ?>">Caption (optional)</label>
        <input id="caption-<?= \AlumniConnect\Support\View::escape($source) ?>-<?= \AlumniConnect\Support\View::escape($eventId) ?>" name="caption" type="text" maxlength="300">
        <button class="event-rsvp-button" type="submit">Upload for review</button>
    </form>

    <?php if ($eventCommunity['photos'] !== []): ?>
        <div class="event-photo-grid">
            <?php foreach ($eventCommunity['photos'] as $photo): ?>
                <figure class="event-photo">
                    <?php if ($photo['moderation_status'] !== 'rejected'): ?>
                        <img src="/memory/photo?id=<?= \AlumniConnect\Support\View::escape($photo['id']) ?>" alt="<?= \AlumniConnect\Support\View::escape($photo['caption'] !== '' ? $photo['caption'] : 'Member photo from ' . $event['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="event-photo-rejected">This photo was not approved.</div>
                    <?php endif; ?>
                    <figcaption>
                        <?php if ($photo['caption'] !== ''): ?>
                            <span><?= \AlumniConnect\Support\View::escape($photo['caption']) ?></span>
                        <?php endif; ?>
                        <small>Shared by <?= \AlumniConnect\Support\View::escape($photo['uploader_name']) ?>
                            <?php if ($photo['moderation_status'] === 'pending'): ?> · Awaiting approval<?php endif; ?>
                            <?php if ($photo['moderation_status'] === 'rejected'): ?> · Not approved<?php endif; ?>
                        </small>
                        <?php if ((bool) $photo['can_delete']): ?>
                            <form action="/events/photo/delete" method="post">
                                <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                <input type="hidden" name="event_id" value="<?= \AlumniConnect\Support\View::escape($eventId) ?>">
                                <input type="hidden" name="photo_id" value="<?= \AlumniConnect\Support\View::escape($photo['id']) ?>">
                                <input type="hidden" name="source" value="<?= \AlumniConnect\Support\View::escape($source) ?>">
                                <button class="event-remove-button" type="submit">Remove photo</button>
                            </form>
                        <?php endif; ?>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="event-community-empty">No approved photos yet.</p>
    <?php endif; ?>
</section>
