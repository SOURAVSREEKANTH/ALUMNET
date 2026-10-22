# Testing Strategy

## Current status

The Phase 2 public landing page, Phase 3 authentication/database foundation, Phase 4 profile/directory foundation, Phase 5 event/RSVP workflows, and Phase 6 attendance features are served locally using PHP's built-in development server. PHP 8.5.11 x64 NTS was downloaded from the official PHP for Windows distribution to the current user's local application-data directory. A separate MySQL 8.0.46 instance is bound to loopback on port 3307; the schema and app PDO connection are verified.

Record only checks actually executed:

- PHP CLI version check: PHP 8.5.11 reported successfully.
- `php -l .\public\index.php`: passed; no syntax errors.
- `node --check .\public\assets\js\site.js`: passed.
- HTTP `GET /`: returned 200 and the expected page title.
- HTTP `HEAD` for the CSS, JavaScript, and both image assets: all returned 200 with expected content types.
- Browser inspection at 1440px and 390px viewport widths: page rendered; document content width matched the available client width (no page-level horizontal overflow).
- Browser interaction: mobile navigation opened, exposed `aria-expanded=true`, and closed on Escape.
- Browser keyboard interaction: the horizontally scrolling memory gallery received keyboard focus and moved when the ArrowRight key was pressed.
- Browser behavior: both local photographs loaded; no browser page errors were reported.
- Reduced-motion emulation: the hero image did not receive the scroll parallax transform.
- Visual screenshots were inspected at desktop and mobile widths.
- Accessibility: semantic landmarks, skip link, image alternatives, visible focus treatment, and keyboard menu behavior were checked in source/browser; no formal automated accessibility audit has been run.

### Phase 3 authentication/database checks

- PDO MySQL loaded successfully with the downloaded PHP runtime; an app PDO connection succeeded.
- `database/setup-local.php` created the fresh development schema and database-scoped app user. The PHP 8.5 PDO multi-statement constant compatibility adjustment was made; the setup script is intentionally not rerun over the existing database.
- All four demo roles were seeded; running the seeder a second time preserved credentials and account status.
- Registration created and persisted a student account/dashboard; login and POST logout worked. A temporary QA account was removed afterward.
- Alumni registration shows pending verification; self-registering `admin` was rejected with HTTP 422.
- A POST without a valid CSRF token returned HTTP 403.
- Dashboard access without authentication redirects to login. GET `/logout` is rejected; logout is a CSRF-protected POST.
- A malicious registration name rendered escaped (`&lt;script&gt;`) rather than executable markup.
- Login attempts 1–8 were accepted for recording and the next check was blocked at the configured threshold; synthetic test rows were removed afterward.
- Login regenerated the session identifier; the session cookie was HttpOnly. Session cookie policy also sets SameSite=Lax.
- All four seeded roles authenticated and showed the expected role; alumni displayed verification pending.
- The refreshed demo credentials were also used to verify successful login and dashboard access for all four roles.
- PHP 8.5.11 with PDO MySQL, isolated MySQL 8.0.46 and the current browser run were used for these checks. No formal PHPUnit suite or automated accessibility audit exists yet.
- Phase 3 closeout: PHP syntax passed for the then-current 27 PHP files; `node --check` passed for the public JavaScript.
- Final HTTP regression: `GET /` returned 200; unauthenticated `GET /dashboard` redirected with 303 to `/login`; `GET /logout` returned 405; `POST /register` without CSRF returned 403; requests for `/config/local.php` and `/database/schema.sql` returned 404.
- Final configuration check: PDO MySQL and the app connection succeeded; all four role types and the authentication-attempt cleanup index were present. The local config exists and is listed in `.gitignore`; its contents were not displayed.
- The PHP 8.5 setup option check confirmed `Pdo\Mysql::ATTR_MULTI_STATEMENTS` is available. The existing-database setup script was not rerun because it deliberately refuses to overwrite its configured database or local credentials.
### Phase 4 profile and directory checks

- The existing database migration created `member_profiles` and backfilled the four existing demo accounts. Rerunning it reported zero additional profiles; existing rows were not overwritten.
- New account creation now creates a member profile in the same registration transaction.
- Temporary student and alumni accounts exercised profile editing through authenticated HTTP requests. Test users and temporary catalog records were removed afterward.
- Private profiles were absent from search. Members-visible profiles appeared; a shared department and graduating year produced the explicit “Same department and graduating year” match reason.
- Account email remained hidden until the profile owner enabled contact sharing; opting in exposed it to directory members.
- Search treated `%` as a literal character. Cross-department batch selection returned HTTP 422. A profile POST without CSRF returned HTTP 403.
- Anonymous requests to `/profile` and `/directory` redirected to sign-in. Profile text containing `<script>` was stored and rendered escaped.
- Department and year filters returned 21 matching synthetic records. Browser pagination showed 20 records on page 1 and the remaining record on page 2.
- Browser inspection of the directory at desktop and 390px mobile widths showed no horizontal overflow. The profile editor rendered its controls and privacy choices. Pending alumni status was clearly labeled. No automated accessibility audit was run.
- `run-local.ps1` now validates PHP 8+ and PDO MySQL before starting; `run-mysql-local.ps1` provides the matching isolated database startup command.
- Final Phase 4 static checks: PHP syntax passed for all 32 PHP files; `node --check` passed; both PowerShell startup scripts parsed successfully.
- Final route checks: anonymous requests to `/profile` and `/directory` redirected to `/login`; `GET /logout` remained 405; sensitive local configuration and SQL files remained unavailable publicly.
- The migration was rerun after QA cleanup and backfilled zero rows. Four existing user profiles remained present, matching the four local demo accounts; temporary test users and catalog rows were removed.

### Phase 5 events and RSVP checks

- `database/migrate-phase5.php` was applied twice using the local MySQL administrator account; both runs succeeded and existing member data was preserved.
- All 36 PHP files passed syntax validation; `node --check .\public\assets\js\site.js` passed.
- Repository/MySQL integration checks verified organizer-created event persistence and audit logging, duplicate RSVP idempotence, one-seat capacity enforcement, interest without seat reservation, FIFO waitlist position, seat promotion after cancellation or switching to interested, waitlist counts, unlimited capacity, and rejection of missing events.
- Three parallel RSVP processes competing for a one-seat event produced exactly one attending RSVP and two waitlisted members. Cancelling an attendee promoted the first active waitlisted member in the sequential transition check.
- HTTP authorization checks verified anonymous `/events` redirects to login, the organizer sees publishing controls, an event-create GET returns 405, publishing without CSRF returns 403, and a signed-in student receives 403 when directly posting an event despite a valid CSRF token.
- Browser workflow logged in as a temporary organizer, published a capacity-limited event, rendered it in the signed-in event list, RSVP'd and cancelled through the UI, and confirmed cancellation status. An event title containing script markup rendered as escaped text with no executable script element.
- Browser inspection at a 390px mobile viewport measured a 375px client width and 375px document width; no horizontal overflow or inserted inline script was present.
- Temporary event, audit row, organizer/member test accounts, RSVP and waitlist data were deleted after validation. No test events or browser QA accounts remain.

### Phase 6 attendance checks

- `database/migrate-phase6.php` had already been applied twice; the local app connection confirmed `event_passes` and `event_checkins` exist. The current empty-state counts after QA cleanup are 4 users, 0 events, 0 passes, and 0 check-ins.
- All 40 PHP files passed `php -l`; `node --check` passed for `site.js`, `event-tools.js`, and the bundled `qrcode.min.js`.
- A disposable local MySQL integration fixture verified pass issuance, SHA-256-only token storage, expiry rejection, organizer ownership, QR check-in, case-insensitive codes, duplicate/invalid pass rejection, and aggregate report counts/rate. The fixture users, event, pass, check-in, and audit row were removed; post-test counts returned to the prior empty event/attendance state.
- HTTP smoke checks: `GET /events` returned 200; unauthenticated check-in and report requests redirected to `/login`; `GET /events/pass` returned 405 with `Allow: POST`; the event-tools script returned 200 as JavaScript. Responses confirmed `Permissions-Policy: camera=(self)`.
- Browser workflow logged in as a temporary attendee, opened the event pass, and verified that the locally generated QR image rendered after the CSP was updated to allow `data:` images. The organizer event list showed the check-in/report links, and the check-in page rendered its manual-entry form.
- The integrated browser did not support `BarcodeDetector`; clicking the scanner control displayed the manual-entry fallback. The manual-entry form rendered, but a manual-code submission, live camera scan, and Phase 6 mobile/responsive screenshots were not verified.

### Phase 7 mentorship, opportunities, and meetup checks

- `database/migrate-phase7.php` was applied twice. Both runs completed and preserved existing member/event data; the new mentor, request, opportunity, and event-preference tables are available.
- The Phase 7 repository/MySQL checks verified that verified alumni can offer mentorship and publish opportunities while pending alumni and students cannot; mentors are ranked by shared department/graduating year with the reason shown; duplicate active mentorship requests are rejected; only the assigned mentor can decide; opportunity listings can be closed only by their publisher or an administrator; and meetup suggestions apply saved venue/date filters and explain the matching criteria.
- A temporary local browser workflow signed in as a student, opened `/community`, displayed the mentor/opportunity listings and venue/date match explanation, sent a mentorship request, and showed the request as pending. The community page's document width matched the client width at 375px mobile and 1425px desktop sizes (no horizontal overflow).
- Final local checks passed for syntax in all 44 PHP files and all 3 checked JavaScript files. The Phase 7 schema tables were confirmed in MySQL, the no-preferences event-match query executed successfully, an unauthenticated `GET /community` returned `303` to `/login`, and `GET /mentorship/request` returned `405`.
- Temporary Phase 7 user accounts, event, opportunity, mentorship profile/request, preference, audit, and login-attempt fixtures were deleted. Database counts returned to 4 users and zero events, mentor profiles, mentorship requests, opportunities, and preferences.
- Mentor decision and opportunity publishing/closing forms, authenticated CSRF rejection and role-denial HTTP behavior, and saved visual screenshots have not yet been verified end to end in the browser. The anonymous redirect and route-method checks above do not substitute for those authenticated checks.

## Planned test layers

### Static and unit checks

- Run PHP syntax checks on changed PHP files.
- Test input normalization and validation boundaries.
- Test session/authentication and role authorization rules.
- Test CSRF token rejection and acceptance.
- Test matching recommendations against known, transparent rules.
- Test RSVP, waitlist, mentorship, and moderation state transitions.
- Check output-escaping helpers for text and attribute contexts.

### MySQL integration tests

- Apply schema from a clean database and run the seeder twice safely where idempotence is intended.
- Verify foreign keys and unique account/email, RSVP, waitlist, pass, and check-in constraints.
- Exercise event capacity under concurrent RSVP attempts; ensure the capacity is never exceeded.
- Verify waitlist ordering and promotion after cancellation.
- Verify duplicate check-in is rejected and check-in is authorized/audited.
- Verify analytics distinguish RSVP intent from physical attendance.
- Ensure restricted profiles and hidden attendee identities are not returned by directory/event queries.

Integration tests must use a dedicated test database and must not target real user data.

### HTTP and browser checks

- Registration, login, logout, session rotation, and role-specific access.
- Reject unauthenticated and insufficient-role requests even when called directly.
- Reject state-changing requests without a valid CSRF token.
- Check persisted profile search, event RSVP state, and dashboard values.
- Validate upload size/type/content restrictions and verify stored files cannot execute.
- Check keyboard navigation, visible focus, contrast, labels, and error announcements.
- Test narrow mobile viewports and reduced-motion preferences.
- Verify public interactions degrade gracefully if JavaScript/animation fails.

Use browser inspection or automation for rendered behavior. Source review alone is not visual verification.

## Phase exit criteria

Each implementation phase should add focused tests for its core invariants and pass those tests before the next dependent phase begins. Record actual commands, environment, results, and known failures here; never list an unrun check as passed.
