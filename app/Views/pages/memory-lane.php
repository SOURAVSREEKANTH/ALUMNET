<section class="events-section section-pad" aria-labelledby="memory-lane-title">
    <div class="events-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Back to the good parts</p>
        <h1 id="memory-lane-title">Memory<br><em>lane.</em></h1>
        <p>Remember gatherings that have started with event discussions and member-shared photos. This member-only space shows approved photos; your own pending uploads remain visible to you.</p>
    </div>
    <div class="events-results-heading" aria-live="polite">
        <p>STARTED EVENTS</p>
        <p><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?></p>
    </div>
    <?php if ($events === []): ?>
        <div class="events-empty">
            <h2>No past events yet.</h2>
            <p>Published gatherings will appear here after they end.</p>
        </div>
    <?php else: ?>
        <div class="event-grid">
            <?php foreach ($events as $event): ?>
                <article class="event-card" id="memory-event-<?= \AlumniConnect\Support\View::escape($event['id']) ?>">
                    <div class="event-card-meta">
                        <p><?= \AlumniConnect\Support\View::escape($event['starts_label']) ?></p>
                        <p>Organized by <?= \AlumniConnect\Support\View::escape($event['organizer_name']) ?></p>
                    </div>
                    <h2><?= \AlumniConnect\Support\View::escape($event['title']) ?></h2>
                    <p class="event-venue"><?= \AlumniConnect\Support\View::escape($event['venue']) ?></p>
                    <?php
                    $isMemoryLane = true;
                    require dirname(__DIR__) . '/partials/event-community.php';
                    ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
