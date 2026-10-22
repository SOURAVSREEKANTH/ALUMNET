<section class="event-tool-section section-pad" aria-labelledby="event-report-title">
    <div class="event-tool-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Organizer tools</p>
        <h1 id="event-report-title">Attendance<br><em>report.</em></h1>
        <p><?= \AlumniConnect\Support\View::escape($report['title']) ?> · <?= \AlumniConnect\Support\View::escape($report['venue']) ?></p>
        <p><?= \AlumniConnect\Support\View::escape($startsLabel) ?> — <?= \AlumniConnect\Support\View::escape($endsLabel) ?></p>
    </div>
    <div class="event-report-panel">
        <p class="event-report-note">RSVPs describe member intent. Check-ins record actual attendance. Attendee names are not included in this summary.</p>
        <dl class="event-report-grid">
            <div>
                <dt>Confirmed RSVPs</dt>
                <dd><?= \AlumniConnect\Support\View::escape($report['attending_count']) ?></dd>
            </div>
            <div>
                <dt>Actual check-ins</dt>
                <dd><?= \AlumniConnect\Support\View::escape($report['checked_in_count']) ?></dd>
            </div>
            <div>
                <dt>RSVP-to-check-in rate</dt>
                <dd><?= $report['conversion_rate'] === null ? 'N/A' : \AlumniConnect\Support\View::escape($report['conversion_rate']) . '%' ?></dd>
            </div>
            <div>
                <dt>Interested, not attending</dt>
                <dd><?= \AlumniConnect\Support\View::escape($report['interested_count']) ?></dd>
            </div>
            <div>
                <dt>Currently waitlisted</dt>
                <dd><?= \AlumniConnect\Support\View::escape($report['waiting_count']) ?></dd>
            </div>
            <div>
                <dt>Waitlist RSVP state</dt>
                <dd><?= \AlumniConnect\Support\View::escape($report['waitlisted_rsvp_count']) ?></dd>
            </div>
            <div>
                <dt>Event capacity</dt>
                <dd><?= $report['capacity'] === null ? 'Unlimited' : \AlumniConnect\Support\View::escape($report['capacity']) ?></dd>
            </div>
            <div>
                <dt>Event status</dt>
                <dd><?= \AlumniConnect\Support\View::escape(ucfirst($report['status'])) ?></dd>
            </div>
        </dl>
        <a class="event-rsvp-button" href="/organizer/check-in?event=<?= \AlumniConnect\Support\View::escape($report['id']) ?>">Open check-in desk <span aria-hidden="true">↗</span></a>
    </div>
    <a class="event-back-link" href="/events">← Back to events</a>
</section>
