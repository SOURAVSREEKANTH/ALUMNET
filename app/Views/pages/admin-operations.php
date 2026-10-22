<section class="admin-operations section-pad" aria-labelledby="admin-operations-title">
    <div class="community-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Keep the network healthy</p>
        <h1 id="admin-operations-title">Admin<br><em>operations.</em></h1>
        <p>Review alumni verification, moderate new event photos, and check high-level service activity. Decisions are recorded in the audit log.</p>
    </div>

    <section class="admin-metric-grid" aria-label="Operational summary">
        <?php foreach ([
            'Active accounts' => $summary['active_accounts'],
            'Pending alumni' => $summary['pending_alumni'],
            'Upcoming events' => $summary['upcoming_events'],
            'Confirmed RSVPs' => $summary['confirmed_rsvps'],
            'Check-ins (30 days)' => $summary['checkins_last_30_days'],
            'Open opportunities' => $summary['open_opportunities'],
            'Photos awaiting review' => $summary['pending_photos'],
        ] as $label => $value): ?>
            <article class="admin-metric">
                <p><?= \AlumniConnect\Support\View::escape($label) ?></p>
                <strong><?= \AlumniConnect\Support\View::escape($value) ?></strong>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="admin-queue" aria-labelledby="alumni-review-title">
        <div class="admin-queue-heading">
            <h2 id="alumni-review-title">Alumni verification</h2>
            <p>Approve only according to the institution's verification policy. Do not use profile visibility or an email address alone as graduation proof.</p>
        </div>
        <?php if ($pendingAlumni === []): ?>
            <div class="community-empty">
                <h3>No accounts are awaiting verification.</h3>
                <p>New alumni registrations with pending status will appear here.</p>
            </div>
        <?php else: ?>
            <div class="admin-queue-grid">
                <?php foreach ($pendingAlumni as $alumnus): ?>
                    <article class="admin-queue-card">
                        <h3><?= \AlumniConnect\Support\View::escape($alumnus['full_name']) ?></h3>
                        <p><?= \AlumniConnect\Support\View::escape($alumnus['email']) ?></p>
                        <?php if ($alumnus['job_title'] !== null || $alumnus['company'] !== null): ?>
                            <p><?= \AlumniConnect\Support\View::escape(trim(($alumnus['job_title'] ?? '') . ' ' . ($alumnus['company'] ?? ''))) ?></p>
                        <?php endif; ?>
                        <small>Account created <?= \AlumniConnect\Support\View::escape($alumnus['created_at']) ?> UTC · Pending</small>
                        <form class="admin-decision-form" action="/admin/alumni-review" method="post">
                            <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                            <input type="hidden" name="user_id" value="<?= \AlumniConnect\Support\View::escape($alumnus['id']) ?>">
                            <button class="event-rsvp-button" type="submit" name="decision" value="verified">Verify</button>
                            <button class="event-rsvp-button event-rsvp-secondary" type="submit" name="decision" value="rejected">Reject</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-queue" aria-labelledby="photo-review-title">
        <div class="admin-queue-heading">
            <h2 id="photo-review-title">Photo moderation</h2>
            <p>Pending images are private to their uploader and moderators until approval. Rejected images stay hidden from other members.</p>
        </div>
        <?php if ($pendingPhotos === []): ?>
            <div class="community-empty">
                <h3>No photos are awaiting review.</h3>
                <p>New event-memory uploads will appear here.</p>
            </div>
        <?php else: ?>
            <div class="admin-photo-grid">
                <?php foreach ($pendingPhotos as $photo): ?>
                    <article class="admin-photo-card">
                        <img src="/memory/photo?id=<?= \AlumniConnect\Support\View::escape($photo['id']) ?>" alt="<?= \AlumniConnect\Support\View::escape($photo['caption'] !== '' ? $photo['caption'] : 'Uploaded photo for ' . $photo['event_title']) ?>" loading="lazy">
                        <div class="admin-photo-details">
                            <h3><?= \AlumniConnect\Support\View::escape($photo['event_title']) ?></h3>
                            <p>Uploaded by <?= \AlumniConnect\Support\View::escape($photo['uploader_name']) ?></p>
                            <?php if ($photo['caption'] !== ''): ?>
                                <p><?= \AlumniConnect\Support\View::escape($photo['caption']) ?></p>
                            <?php endif; ?>
                            <small><?= \AlumniConnect\Support\View::escape($photo['created_at']) ?> UTC</small>
                            <form class="admin-decision-form" action="/admin/photo-review" method="post">
                                <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                <input type="hidden" name="photo_id" value="<?= \AlumniConnect\Support\View::escape($photo['id']) ?>">
                                <button class="event-rsvp-button" type="submit" name="decision" value="approved">Approve</button>
                                <button class="event-rsvp-button event-rsvp-secondary" type="submit" name="decision" value="rejected">Reject</button>
                            </form>
                            <details class="admin-delete-details">
                                <summary>Delete permanently</summary>
                                <form action="/admin/photo-delete" method="post">
                                    <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                    <input type="hidden" name="photo_id" value="<?= \AlumniConnect\Support\View::escape($photo['id']) ?>">
                                    <button class="event-remove-button" type="submit">Confirm photo and file deletion</button>
                                </form>
                            </details>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
