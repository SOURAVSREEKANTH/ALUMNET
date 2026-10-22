<?php
$csrfToken = \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token());
$opportunityTypes = [
    'job' => 'Job',
    'internship' => 'Internship',
    'scholarship' => 'Scholarship',
    'volunteer' => 'Volunteer',
    'other' => 'Other',
];
?>
<section class="community-section section-pad" aria-labelledby="community-title">
    <div class="community-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Find a way forward together</p>
        <h1 id="community-title">Grow your<br><em>connections.</em></h1>
        <p>Explore verified alumni mentors, share professional opportunities, and find gatherings that fit the preferences you choose.</p>
    </div>

    <section class="community-panel" id="meetups" aria-labelledby="meetups-title">
        <div class="community-panel-heading">
            <p class="eyebrow"><span class="eyebrow-line"></span> Preference-based suggestions</p>
            <h2 id="meetups-title">Meet up when it works.</h2>
            <p>Choose a venue keyword and date window. Suggestions match only these saved preferences; no hidden traits are inferred.</p>
        </div>
        <form class="community-form" action="/community/preferences" method="post">
            <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
            <div class="profile-form-grid">
                <div class="field-group">
                    <label for="preferred-location">Venue or area contains</label>
                    <input id="preferred-location" name="preferred_location" type="text" maxlength="120" value="<?= \AlumniConnect\Support\View::escape($eventPreferences['preferred_location'] ?? '') ?>" placeholder="Optional keyword">
                </div>
                <div class="field-group">
                    <label for="available-from">Available from</label>
                    <input id="available-from" name="available_from" type="date" value="<?= \AlumniConnect\Support\View::escape($eventPreferences['available_from'] ?? '') ?>">
                </div>
                <div class="field-group">
                    <label for="available-until">Available until</label>
                    <input id="available-until" name="available_until" type="date" value="<?= \AlumniConnect\Support\View::escape($eventPreferences['available_until'] ?? '') ?>">
                </div>
            </div>
            <button class="auth-submit" type="submit">Save meetup preferences <span aria-hidden="true">↗</span></button>
        </form>
        <?php if ($matchingEvents === []): ?>
            <div class="community-empty">
                <h3>No upcoming gatherings match those preferences.</h3>
                <p>Change or clear your date and location filters to see other published events.</p>
            </div>
        <?php else: ?>
            <div class="community-card-grid">
                <?php foreach ($matchingEvents as $event): ?>
                    <article class="community-card">
                        <p class="community-card-meta"><?= \AlumniConnect\Support\View::escape((new DateTimeImmutable($event['starts_at'], new DateTimeZone('UTC')))->format('D, M j, Y \a\t H:i \U\T\C')) ?></p>
                        <h3><?= \AlumniConnect\Support\View::escape($event['title']) ?></h3>
                        <p><?= \AlumniConnect\Support\View::escape($event['venue']) ?></p>
                        <p class="community-match-reason">Suggested because: <?= \AlumniConnect\Support\View::escape($event['match_reason']) ?></p>
                        <a class="text-link" href="/events#event-<?= \AlumniConnect\Support\View::escape($event['id']) ?>">View event <span aria-hidden="true">↗</span></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="community-panel" id="mentorship" aria-labelledby="mentorship-title">
        <div class="community-panel-heading">
            <p class="eyebrow"><span class="eyebrow-line"></span> Learn from experience</p>
            <h2 id="mentorship-title">Mentorship.</h2>
            <p>Verified alumni can offer guidance. Student suggestions are ranked by shared department and graduating year, with the reason shown on each profile.</p>
        </div>

        <?php if ($canOfferMentorship): ?>
            <form class="community-form" action="/community/mentor-profile" method="post">
                <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                <div class="field-group">
                    <label for="mentor-topics">Topics you can help with</label>
                    <textarea id="mentor-topics" name="topics" rows="3" maxlength="500" minlength="3" required><?= \AlumniConnect\Support\View::escape($mentorProfile['topics']) ?></textarea>
                    <p class="form-hint">Describe areas of experience, separated by commas (up to 500 characters).</p>
                </div>
                <div class="field-group">
                    <label for="mentor-availability">Profile status</label>
                    <select id="mentor-availability" name="is_available">
                        <option value="1" <?= (int) $mentorProfile['is_available'] === 1 ? 'selected' : '' ?>>Available for requests</option>
                        <option value="0" <?= (int) $mentorProfile['is_available'] !== 1 ? 'selected' : '' ?>>Pause new requests</option>
                    </select>
                </div>
                <button class="auth-submit" type="submit">Save mentor profile <span aria-hidden="true">↗</span></button>
            </form>
        <?php elseif (in_array('alumni', array_column($currentUser['roles'], 'slug'), true)): ?>
            <p class="community-note">Mentor profiles are available after administrator verification of your alumni account.</p>
        <?php endif; ?>

        <?php if ($canRequestMentorship): ?>
            <?php if ($mentors === []): ?>
                <div class="community-empty">
                    <h3>No mentors are currently available.</h3>
                    <p>Verified alumni who opt in will appear here. You can still browse opportunities and event suggestions below.</p>
                </div>
            <?php else: ?>
                <div class="community-card-grid">
                    <?php foreach ($mentors as $mentor): ?>
                        <article class="community-card">
                            <p class="community-card-meta">Verified alumni mentor</p>
                            <h3><?= \AlumniConnect\Support\View::escape($mentor['full_name']) ?></h3>
                            <?php if ($mentor['headline'] !== null && $mentor['headline'] !== ''): ?>
                                <p><?= \AlumniConnect\Support\View::escape($mentor['headline']) ?></p>
                            <?php endif; ?>
                            <?php if ($mentor['job_title'] !== null || $mentor['company'] !== null): ?>
                                <p><?= \AlumniConnect\Support\View::escape(implode(' · ', array_filter([$mentor['job_title'], $mentor['company']]))) ?></p>
                            <?php endif; ?>
                            <p><strong>Topics:</strong> <?= \AlumniConnect\Support\View::escape($mentor['topics']) ?></p>
                            <?php if ($mentor['department_name'] !== null): ?>
                                <p><?= \AlumniConnect\Support\View::escape($mentor['department_name']) ?><?= $mentor['graduation_year'] === null ? '' : ' · ' . \AlumniConnect\Support\View::escape($mentor['graduation_year']) ?></p>
                            <?php endif; ?>
                            <p class="community-match-reason">Match: <?= \AlumniConnect\Support\View::escape($mentor['match_reason']) ?></p>
                            <?php if ($mentor['request_status'] === 'pending' || $mentor['request_status'] === 'accepted'): ?>
                                <p class="community-note">Your request is <?= \AlumniConnect\Support\View::escape($mentor['request_status']) ?>.</p>
                            <?php else: ?>
                                <form class="community-form community-request-form" action="/mentorship/request" method="post">
                                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="mentor_id" value="<?= \AlumniConnect\Support\View::escape($mentor['id']) ?>">
                                    <label for="mentor-goals-<?= \AlumniConnect\Support\View::escape($mentor['id']) ?>">What would you like guidance on?</label>
                                    <textarea id="mentor-goals-<?= \AlumniConnect\Support\View::escape($mentor['id']) ?>" name="goals" rows="3" minlength="20" maxlength="2000" required></textarea>
                                    <button class="directory-submit" type="submit">Request mentorship <span aria-hidden="true">↗</span></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($mentor['contact_email'] !== null): ?>
                                <a class="member-contact" href="mailto:<?= \AlumniConnect\Support\View::escape($mentor['contact_email']) ?>">Contact by email <span aria-hidden="true">↗</span></a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="community-panel" id="requests" aria-labelledby="requests-title">
        <div class="community-panel-heading">
            <p class="eyebrow"><span class="eyebrow-line"></span> Keep track of introductions</p>
            <h2 id="requests-title">Mentorship requests.</h2>
        </div>
        <?php if ($requests === []): ?>
            <p class="community-note">Requests you send or receive will appear here.</p>
        <?php else: ?>
            <div class="community-card-grid">
                <?php foreach ($requests as $request): ?>
                    <article class="community-card">
                        <p class="community-card-meta"><?= (int) $request['can_decide'] === 1 ? 'Incoming request' : 'Request sent' ?></p>
                        <h3><?= (int) $request['can_decide'] === 1 ? \AlumniConnect\Support\View::escape($request['requester_name']) : \AlumniConnect\Support\View::escape($request['mentor_name']) ?></h3>
                        <p><?= nl2br(\AlumniConnect\Support\View::escape($request['goals'])) ?></p>
                        <p class="community-note">Status: <?= \AlumniConnect\Support\View::escape(ucfirst($request['status'])) ?></p>
                        <?php if ((int) $request['can_decide'] === 1 && $request['status'] === 'pending'): ?>
                            <div class="community-inline-actions">
                                <?php foreach (['accepted' => 'Accept', 'declined' => 'Decline'] as $decision => $label): ?>
                                    <form action="/mentorship/decision" method="post">
                                        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="request_id" value="<?= \AlumniConnect\Support\View::escape($request['id']) ?>">
                                        <input type="hidden" name="decision" value="<?= \AlumniConnect\Support\View::escape($decision) ?>">
                                        <button class="directory-submit" type="submit"><?= \AlumniConnect\Support\View::escape($label) ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="community-panel" id="opportunities" aria-labelledby="opportunities-title">
        <div class="community-panel-heading">
            <p class="eyebrow"><span class="eyebrow-line"></span> Share a useful next step</p>
            <h2 id="opportunities-title">Opportunities.</h2>
            <p>Browse current job, internship, scholarship, and volunteer listings shared by verified alumni and authorized organizers.</p>
        </div>
        <?php if ($canPublishOpportunities): ?>
            <details class="event-publisher">
                <summary>Share an opportunity <span aria-hidden="true">＋</span></summary>
                <div class="profile-panel event-publisher-panel">
                    <form action="/opportunities/create" method="post">
                        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                        <div class="profile-form-grid">
                            <div class="field-group field-full">
                                <label for="opportunity-title">Title</label>
                                <input id="opportunity-title" name="title" type="text" minlength="4" maxlength="160" required>
                            </div>
                            <div class="field-group">
                                <label for="opportunity-type">Opportunity type</label>
                                <select id="opportunity-type" name="opportunity_type" required>
                                    <?php foreach ($opportunityTypes as $type => $label): ?>
                                        <option value="<?= \AlumniConnect\Support\View::escape($type) ?>"><?= \AlumniConnect\Support\View::escape($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="opportunity-location">Location</label>
                                <input id="opportunity-location" name="location" type="text" maxlength="160">
                            </div>
                            <div class="field-group">
                                <label for="opportunity-expiry">Closes (UTC, optional)</label>
                                <input id="opportunity-expiry" name="expires_at" type="datetime-local">
                            </div>
                            <div class="field-group field-full">
                                <label for="opportunity-description">Description and how to follow up</label>
                                <textarea id="opportunity-description" name="description" rows="5" minlength="20" maxlength="4000" required></textarea>
                            </div>
                        </div>
                        <button class="auth-submit" type="submit">Publish opportunity <span aria-hidden="true">↗</span></button>
                    </form>
                </div>
            </details>
        <?php endif; ?>

        <?php if ($opportunities === []): ?>
            <div class="community-empty">
                <h3>No open opportunities are listed.</h3>
                <p>New listings will appear here when verified alumni or organizers share them.</p>
            </div>
        <?php else: ?>
            <div class="community-card-grid">
                <?php foreach ($opportunities as $opportunity): ?>
                    <article class="community-card" id="opportunity-<?= \AlumniConnect\Support\View::escape($opportunity['id']) ?>">
                        <p class="community-card-meta"><?= \AlumniConnect\Support\View::escape($opportunityTypes[$opportunity['opportunity_type']] ?? 'Opportunity') ?> · Shared by <?= \AlumniConnect\Support\View::escape($opportunity['author_name']) ?></p>
                        <h3><?= \AlumniConnect\Support\View::escape($opportunity['title']) ?></h3>
                        <?php if ($opportunity['location'] !== null && $opportunity['location'] !== ''): ?>
                            <p><?= \AlumniConnect\Support\View::escape($opportunity['location']) ?></p>
                        <?php endif; ?>
                        <p><?= nl2br(\AlumniConnect\Support\View::escape($opportunity['description'])) ?></p>
                        <?php if ($opportunity['expires_at'] !== null): ?>
                            <p class="community-note">Closes <?= \AlumniConnect\Support\View::escape((new DateTimeImmutable($opportunity['expires_at'], new DateTimeZone('UTC')))->format('M j, Y \a\t H:i \U\T\C')) ?></p>
                        <?php endif; ?>
                        <?php if ((int) $opportunity['can_close'] === 1): ?>
                            <form action="/opportunities/close" method="post">
                                <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                                <input type="hidden" name="opportunity_id" value="<?= \AlumniConnect\Support\View::escape($opportunity['id']) ?>">
                                <button class="directory-submit" type="submit">Close listing</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
