<section class="event-tool-section section-pad" aria-labelledby="event-pass-title">
    <div class="event-tool-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Your place is confirmed</p>
        <h1 id="event-pass-title">Your event<br><em>pass.</em></h1>
        <p>Show this QR code to the event organizer when you arrive. The pass is private, valid only for this event, and expires at the event end time.</p>
    </div>
    <article class="event-pass-card">
        <p class="event-pass-event"><?= \AlumniConnect\Support\View::escape($event['title']) ?></p>
        <p><?= \AlumniConnect\Support\View::escape($startsLabel) ?></p>
        <p><?= \AlumniConnect\Support\View::escape($event['venue']) ?></p>
        <div class="event-pass-qr" data-pass-code="<?= \AlumniConnect\Support\View::escape($passToken) ?>" aria-label="Single-use QR event pass"></div>
        <p class="event-pass-manual-label">If the camera cannot scan:</p>
        <code class="event-pass-code"><?= \AlumniConnect\Support\View::escape($passToken) ?></code>
        <p class="event-pass-expiry">Valid through <?= \AlumniConnect\Support\View::escape($endsLabel) ?></p>
        <p class="event-pass-warning">This pass code is private. Do not share a screenshot or code with anyone else.</p>
    </article>
    <a class="event-back-link" href="/events">← Back to events</a>
</section>
