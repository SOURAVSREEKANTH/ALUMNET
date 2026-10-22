<section class="events-section section-pad" aria-labelledby="events-title">
    <div class="events-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Make the next memory</p>
        <h1 id="events-title">Meet up<br><em>again.</em></h1>
        <p>Find the next AlumniConnect gathering. RSVP to reserve a place, mark yourself interested without taking a seat, or join a first-in waitlist when a capacity-limited event is full. Times are shown in UTC.</p>
    </div>

    <?php if ($canPublishEvents): ?>
        <details class="event-publisher" id="publish-event" <?= $formError !== null ? 'open' : '' ?>>
            <summary>Publish an event <span aria-hidden="true">＋</span></summary>
            <div class="profile-panel event-publisher-panel">
                <p class="event-time-note">Published events are immediately visible to signed-in members. Enter start and end times in UTC. Leave capacity blank for an unlimited event.</p>
                <?php if ($formError !== null): ?>
                    <p class="form-message" role="alert"><?= \AlumniConnect\Support\View::escape($formError) ?></p>
                <?php endif; ?>
                <form action="/events/create" method="post">
                    <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                    <div class="profile-form-grid">
                        <div class="field-group field-full">
                            <label for="event-title">Event title</label>
                            <input id="event-title" name="title" type="text" minlength="4" maxlength="140" required value="<?= \AlumniConnect\Support\View::escape($formValues['title'] ?? '') ?>">
                        </div>
                        <div class="field-group field-full">
                            <label for="event-description">Description</label>
                            <textarea id="event-description" name="description" rows="5" minlength="20" maxlength="4000" required><?= \AlumniConnect\Support\View::escape($formValues['description'] ?? '') ?></textarea>
                        </div>
                        <div class="field-group field-full">
                            <label for="event-venue">Venue</label>
                            <input id="event-venue" name="venue" type="text" maxlength="200" required value="<?= \AlumniConnect\Support\View::escape($formValues['venue'] ?? '') ?>">
                        </div>
                        <div class="field-group">
                            <label for="event-start">Starts (UTC)</label>
                            <input id="event-start" name="starts_at" type="datetime-local" required value="<?= \AlumniConnect\Support\View::escape($formValues['starts_at'] ?? '') ?>">
                        </div>
                        <div class="field-group">
                            <label for="event-end">Ends (UTC)</label>
                            <input id="event-end" name="ends_at" type="datetime-local" required value="<?= \AlumniConnect\Support\View::escape($formValues['ends_at'] ?? '') ?>">
                        </div>
                        <div class="field-group">
                            <label for="event-capacity">Attendee capacity</label>
                            <input id="event-capacity" name="capacity" type="number" min="1" max="10000" step="1" value="<?= \AlumniConnect\Support\View::escape($formValues['capacity'] ?? '') ?>" aria-describedby="capacity-hint">
                            <p class="form-hint" id="capacity-hint">Optional. Leave blank for unlimited places.</p>
                        </div>
                    </div>
                    <button class="auth-submit" type="submit">Publish event <span aria-hidden="true">↗</span></button>
                </form>
            </div>
        </details>
    <?php endif; ?>

    <div class="events-results-heading" aria-live="polite">
        <p>UPCOMING EVENTS</p>
        <p><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?></p>
    </div>

    <?php if ($events === []): ?>
        <div class="events-empty">
            <h2>No upcoming events just yet.</h2>
            <p>When an organizer publishes a gathering, it will appear here.</p>
            <?php if ($canPublishEvents): ?>
                <a class="text-link" href="#publish-event">Publish the first event <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="event-grid">
            <?php foreach ($events as $event): ?>
                <article class="event-card" id="event-<?= \AlumniConnect\Support\View::escape($event['id']) ?>">
                    <div class="event-card-meta">
                        <p><?= \AlumniConnect\Support\View::escape($event['starts_label']) ?></p>
                        <p>Organized by <?= \AlumniConnect\Support\View::escape($event['organizer_name']) ?></p>
                    </div>
                    <h2><?= \AlumniConnect\Support\View::escape($event['title']) ?></h2>
                    <p class="event-venue"><?= \AlumniConnect\Support\View::escape($event['venue']) ?></p>
                    <p class="event-description"><?= nl2br(\AlumniConnect\Support\View::escape($event['description'])) ?></p>
                    <p class="event-time-range">Ends at <?= \AlumniConnect\Support\View::escape($event['ends_label']) ?></p>
                    <div class="event-capacity">
                        <span>
                            <?php if ($event['capacity'] === null): ?>
                                <?= \AlumniConnect\Support\View::escape($event['attendee_count']) ?> attending · no capacity limit
                            <?php else: ?>
                                <?= \AlumniConnect\Support\View::escape($event['attendee_count']) ?> of <?= \AlumniConnect\Support\View::escape($event['capacity']) ?> places filled
                            <?php endif; ?>
                        </span>
                        <?php if ($event['waitlist_count'] > 0): ?>
                            <span><?= \AlumniConnect\Support\View::escape($event['waitlist_count']) ?> on waitlist</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($event['my_state'] === 'attending'): ?>
                        <p class="event-rsvp-state">Your place is confirmed.</p>
                        <form class="event-pass-form" action="/events/pass" method="post">
                            <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                            <input type="hidden" name="event_id" value="<?= \AlumniConnect\Support\View::escape($event['id']) ?>">
                            <button class="event-rsvp-button" type="submit">Show my QR pass <span aria-hidden="true">↗</span></button>
                        </form>
                        <div class="event-rsvp-actions">
                            <?php foreach (['interested' => 'I’m interested instead', 'decline' => 'Cancel RSVP'] as $action => $label): ?>
                                <form action="/events/rsvp" method="post">
                                    <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                    <input type="hidden" name="event_id" value="<?= \AlumniConnect\Support\View::escape($event['id']) ?>">
                                    <input type="hidden" name="action" value="<?= \AlumniConnect\Support\View::escape($action) ?>">
                                    <button class="event-rsvp-button event-rsvp-secondary" type="submit"><?= \AlumniConnect\Support\View::escape($label) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($event['my_state'] === 'waitlisted'): ?>
                        <p class="event-rsvp-state">Waitlist position <?= \AlumniConnect\Support\View::escape($event['my_waitlist_position']) ?>. Your place will be confirmed if a spot opens.</p>
                        <div class="event-rsvp-actions">
                            <?php foreach (['interested' => 'Leave waitlist', 'decline' => 'Clear response'] as $action => $label): ?>
                                <form action="/events/rsvp" method="post">
                                    <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                    <input type="hidden" name="event_id" value="<?= \AlumniConnect\Support\View::escape($event['id']) ?>">
                                    <input type="hidden" name="action" value="<?= \AlumniConnect\Support\View::escape($action) ?>">
                                    <button class="event-rsvp-button event-rsvp-secondary" type="submit"><?= \AlumniConnect\Support\View::escape($label) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($event['my_state'] === 'interested'): ?>
                        <p class="event-rsvp-state">You’re interested; this does not reserve a place.</p>
                        <div class="event-rsvp-actions">
                            <?php foreach (['attend' => 'Reserve a place', 'decline' => 'Clear response'] as $action => $label): ?>
                                <form action="/events/rsvp" method="post">
                                    <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                    <input type="hidden" name="event_id" value="<?= \AlumniConnect\Support\View::escape($event['id']) ?>">
                                    <input type="hidden" name="action" value="<?= \AlumniConnect\Support\View::escape($action) ?>">
                                    <button class="event-rsvp-button <?= $action === 'attend' ? '' : 'event-rsvp-secondary' ?>" type="submit"><?= \AlumniConnect\Support\View::escape($label) ?><?= $action === 'attend' ? ' ↗' : '' ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($event['my_state'] === 'declined'): ?>
                        <p class="event-rsvp-state">You cancelled your response. You can join again while RSVPs are open.</p>
                        <div class="event-rsvp-actions">
                            <?php foreach (['attend' => 'RSVP', 'interested' => 'I’m interested'] as $action => $label): ?>
                                <form action="/events/rsvp" method="post">
                                    <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                    <input type="hidden" name="event_id" value="<?= \AlumniConnect\Support\View::escape($event['id']) ?>">
                                    <input type="hidden" name="action" value="<?= \AlumniConnect\Support\View::escape($action) ?>">
                                    <button class="event-rsvp-button <?= $action === 'attend' ? '' : 'event-rsvp-secondary' ?>" type="submit"><?= \AlumniConnect\Support\View::escape($label) ?><?= $action === 'attend' ? ' ↗' : '' ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="event-rsvp-actions">
                            <?php foreach (['attend' => ($event['capacity'] !== null && $event['attendee_count'] >= $event['capacity'] ? 'Join waitlist' : 'RSVP'), 'interested' => 'I’m interested'] as $action => $label): ?>
                                <form action="/events/rsvp" method="post">
                                    <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                    <input type="hidden" name="event_id" value="<?= \AlumniConnect\Support\View::escape($event['id']) ?>">
                                    <input type="hidden" name="action" value="<?= \AlumniConnect\Support\View::escape($action) ?>">
                                    <button class="event-rsvp-button <?= $action === 'attend' ? '' : 'event-rsvp-secondary' ?>" type="submit"><?= \AlumniConnect\Support\View::escape($label) ?><?= $action === 'attend' ? ' ↗' : '' ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($event['can_manage']): ?>
                        <nav class="event-management-links" aria-label="Event organizer tools">
                            <a href="/organizer/check-in?event=<?= \AlumniConnect\Support\View::escape($event['id']) ?>">Check-in desk</a>
                            <a href="/organizer/report?event=<?= \AlumniConnect\Support\View::escape($event['id']) ?>">Attendance report</a>
                        </nav>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
