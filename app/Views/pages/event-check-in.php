<section class="event-tool-section section-pad" aria-labelledby="event-check-in-title">
    <div class="event-tool-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Organizer tools</p>
        <h1 id="event-check-in-title">Check<br><em>them in.</em></h1>
        <p><?= \AlumniConnect\Support\View::escape($event['title']) ?> · <?= \AlumniConnect\Support\View::escape($event['venue']) ?></p>
        <p><?= \AlumniConnect\Support\View::escape($startsLabel) ?> — <?= \AlumniConnect\Support\View::escape($endsLabel) ?></p>
    </div>
    <div class="event-tool-panel">
        <h2>Scan a member pass</h2>
        <p class="event-tool-help">Allow camera access and scan the QR pass shown by the attendee. If this browser or device does not support scanning, enter the code manually below.</p>
        <button class="event-rsvp-button scanner-start" type="button" data-scanner-start>Start camera scanner</button>
        <p class="scanner-message" data-scanner-message role="status">Camera scanning is optional; manual pass entry is always available.</p>
        <video class="scanner-video" data-scanner-video playsinline muted hidden aria-label="Live camera view for scanning an event pass"></video>
        <form class="check-in-form" action="/organizer/check-in" method="post" data-check-in-form>
            <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
            <input type="hidden" name="event_id" value="<?= \AlumniConnect\Support\View::escape($event['id']) ?>">
            <input type="hidden" name="method" value="manual" data-check-in-method>
            <label for="pass-code">Event pass code</label>
            <input id="pass-code" name="pass_code" type="text" inputmode="text" autocomplete="off" maxlength="64" minlength="64" pattern="[a-fA-F0-9]{64}" required data-pass-code>
            <p class="form-hint">Codes are one-use, event-specific, and valid only during the event.</p>
            <button class="auth-submit" type="submit">Confirm check-in <span aria-hidden="true">↗</span></button>
        </form>
        <a class="event-back-link" href="/organizer/report?event=<?= \AlumniConnect\Support\View::escape($event['id']) ?>">Open attendance report →</a>
    </div>
</section>
