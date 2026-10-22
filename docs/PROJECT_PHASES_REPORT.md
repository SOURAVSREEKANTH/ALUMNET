# AlumniConnect — Project Phases Report

**Report date:** 22 October 2026
**Reporting scope:** Initial workspace inspection through Phase 8 and the current Phase 9 administration foundation (Phase 6 camera check outstanding; Phase 7 verified)
**Project:** AlumniConnect, a PHP and MySQL college alumni-community portal

## 1. Executive summary

AlumniConnect has progressed from an initial workspace into a working local application foundation. Phases 0–5 are complete; Phase 6 manual attendance is verified, with a supported-device camera scan outstanding; Phase 7 workflows and authorization checks are verified. Phase 8 event discussions, private moderated memory uploads, and in-app notifications are implemented. Phase 9 includes the read-only account audit, alumni verification decisions, photo moderation, and basic operational metrics; broader account/role moderation remains.

The project is currently a **local development prototype**, not a production-ready alumni service. Live camera scanning depends on a supported browser/device; manual code is verified as a fallback. Account suspension/role management, broad moderation, comprehensive quality review, and deployment readiness remain outstanding. Do not enter real community data until deployment, privacy, backup, and institutional verification requirements are addressed.

## 2. Project aim and objectives

### Overall aim

Build a secure, privacy-aware web portal that helps a college’s students and alumni reconnect, find one another, attend community events, and—over later phases—share mentoring, professional, and community opportunities.

### Objectives

- Provide a welcoming, responsive public landing page.
- Establish database-backed accounts and server-enforced roles.
- Let members manage profile information and control profile visibility and contact sharing.
- Help signed-in members discover relevant profiles without exposing private information.
- Let authorized organizers publish events and manage event capacity fairly.
- Distinguish event interest and RSVP intent from verified attendance.
- Provide event-specific, one-use attendee passes and authorized check-in.
- Keep the codebase and local setup understandable and testable as features are added.

## 3. Phase-by-phase report

### Phase 0 — Workspace and environment inspection

**Objective:** Understand the project workspace, available tools, and local development constraints before implementing features.

**Work completed:**

- Inspected the existing workspace and selected a lightweight PHP/MySQL approach suitable for the project.
- Confirmed a local PHP runtime and PDO MySQL support could be used.
- Set up an isolated MySQL development instance bound to `127.0.0.1` on port `3307`.
- Established the local database `alumniconnect_dev`, stored under `%LOCALAPPDATA%\AlumniConnect\mysql-data`.
- Confirmed the PHP built-in server could serve the project locally at `http://127.0.0.1:8000/`.

**Outcome:** The project can be developed and run locally without requiring XAMPP or a separate SQL desktop application. PHP connects to MySQL; MySQL itself persists the database files on disk.

**Status:** Complete.

### Phase 1 — Requirements, architecture, and implementation plan

**Objective:** Define the product direction and a staged plan before building the main features.

**Work completed:**

- Documented the project’s purpose, intended users, core goals, and phased roadmap.
- Outlined the PHP application structure, request flow, route boundaries, database entities, and feature dependencies.
- Set early design and accessibility principles for the public experience.
- Planned security and privacy rules, including server-side authorization, CSRF protection, prepared SQL queries, output escaping, and private local configuration.
- Identified institution-specific data and decisions still needed, such as department/year catalog values and the alumni-verification process.

**Outcome:** The project had a documented design and phased delivery sequence to guide implementation. Phase 1 is planning and design work, not a standalone user-facing feature.

**Status:** The architecture and roadmap were documented. Some review questions in the task list—such as confirming institution-specific verification policy and branding—remain open.

### Phase 2 — Public landing page and design system

**Objective:** Create a clear, responsive public introduction to AlumniConnect and a reusable visual foundation.

**Work completed:**

- Built the public landing page with responsive navigation, project messaging, a feature journey, illustrative memory section, and calls to action.
- Added the shared visual styling and local frontend behavior using CSS and vanilla JavaScript.
- Added keyboard support for navigation and the horizontally scrolling gallery.
- Respected reduced-motion preferences and added local illustrative image assets.

**Outcome:** Visitors can understand the product concept and navigate to sign-in or registration. The page does not depend on a frontend framework.

**Verification recorded:** Browser review at desktop and mobile widths; no page-level horizontal overflow in the checked viewports; keyboard and reduced-motion behavior checked; image loading and local asset responses checked.

**Status:** Complete.

### Phase 3 — Authentication, roles, sessions, and database foundation

**Objective:** Create the secure application foundation for persisted member accounts and protected pages.

**Work completed:**

- Created the MySQL schema and a least-privilege application database account.
- Added account registration and login with password hashing.
- Added role-aware access for alumni, students, event organizers, and administrators.
- Added session handling, session identifier rotation after login, HTTP-only/SameSite cookies, idle timeout, CSRF checks, and login/registration request throttling.
- Added protected local configuration and demo-account seeding.
- Added safe error handling and prevented local configuration and schema files from being served publicly.

**Outcome:** Members can create accounts and sign in; access decisions are made by the server rather than relying on browser controls. Alumni accounts are marked pending verification rather than being treated as verified based on email ownership.

**Verification recorded:** PDO/MySQL connectivity, role login checks, registration, logout, CSRF rejection, access control, session rotation, rate limiting, output escaping, and sensitive-file HTTP checks. Temporary QA accounts and synthetic rate-limit rows were removed.

**Status:** Complete as a local authentication/database foundation. Production deployment hardening and formal automated test infrastructure remain future work.

### Phase 4 — Member profiles and directory

**Objective:** Let members maintain profile information and discover other members while respecting privacy choices.

**Work completed:**

- Added a canonical profile record for each member, with department/year references and professional and biographical fields.
- Added profile editing and visibility options.
- Added an authenticated directory with text search, department/year filters, and pagination.
- Excluded private profiles and showed email only when the member opted in to contact sharing.
- Added transparent suggestions based on shared department and graduation year.
- Kept alumni verification status distinct from profile visibility and account role.

**Outcome:** Members can control how much profile information is shown and use the signed-in directory to find other visible members.

**Verification recorded:** Migration/backfill and repeat migration checks; privacy visibility and email-sharing checks; search/filter/pagination checks; escaping and CSRF checks; desktop/mobile directory inspection. Temporary test accounts and catalog records were removed.

**Status:** Complete. Institution-specific departments and graduation batches still need approved data before real-world use.

### Phase 5 — Events, RSVPs, capacity, and waitlists

**Objective:** Enable authorized event publishing and reliable member registration, including fair handling of limited capacity.

**Work completed:**

- Added event publishing for organizer and administrator roles.
- Added attendee responses for attending, interested, and cancelling.
- Added optional capacity; an interest response does not reserve a place.
- Added a FIFO waitlist and transactional promotion when a confirmed attendee releases a place.
- Added event publication audit logging and event listings that avoid exposing other attendees’ identities.

**Outcome:** Organizers can publish events; members can express intent; limited-capacity registrations are protected from overbooking and waitlist transitions are handled consistently.

**Verification recorded:** Repeat migration; repository/MySQL state-transition checks; concurrent RSVP attempts against a one-seat event; role/CSRF/route checks; browser publish/RSVP/cancel workflow; output escaping; mobile viewport inspection. QA events and accounts were removed.

**Status:** Complete.

### Phase 6 — Event passes, check-in, and attendance reporting

**Objective:** Record actual event attendance separately from RSVP intent and give organizers a practical way to check in attendees.

**Work completed:**

- Added event-specific attendee passes for members with confirmed RSVPs.
- Generated random pass codes and stored only their SHA-256 hashes. Reissuing a pass invalidates the old code.
- Added a pass page with a locally rendered QR code and a manual-code fallback.
- Added organizer/admin check-in by QR code or manual code, with event ownership and event-time validation.
- Added transactional, one-use check-in records and audit logging that does not include the pass secret.
- Added aggregate reports for attending RSVPs, interest, waitlist counts, check-ins, and RSVP-to-check-in rate without displaying attendee identities.
- Fixed the browser security policy so the generated QR image can render and updated the permissions policy so the optional camera scanner is not disabled by the application.

**Outcome:** The application distinguishes an RSVP from a recorded check-in and provides a secure event-scoped attendance workflow.

**Verification recorded:**

- Phase 6 migration was applied twice; the attendance tables exist in the local database.
- All 40 PHP files passed syntax validation. `site.js`, `event-tools.js`, and the bundled QR library passed JavaScript syntax validation.
- Disposable local database tests verified pass issuance, hash-only storage, expired/invalid pass rejection, organizer ownership, successful check-in, duplicate rejection, and report calculations.
- HTTP checks verified protected routes and static script delivery.
- Browser inspection verified the pass page and QR image render and that organizer check-in UI and manual-entry form display.
- Initial browser checks found that `BarcodeDetector` was unavailable; manual submission and responsive checks were still outstanding at that point. Follow-up closeout results are recorded below.
- Disposable test users, event, passes, check-in records, and associated test data were removed after testing. The local database returned to 4 users, 0 events, 0 passes, and 0 check-ins.
- **Follow-up closeout on 22 October:** a disposable browser event was published, RSVP'd, and issued a 64-character pass. Manual check-in succeeded during the event, a second submission was rejected as already checked in, and the attendance report showed 1 confirmed RSVP, 1 check-in, and a 100% check-in rate. The check-in desk had no horizontal overflow at tested 375px and 1425px viewport widths.
- The integrated browser did not expose `BarcodeDetector`; the scanner showed the intended manual-entry fallback. Live camera scanning on supported hardware remains the sole device-specific Phase 6 check. Temporary account/event/pass/check-in/audit data were removed, returning the local database to 5 users and zero events, RSVPs, passes, and check-ins.

**Status:** Core implementation and browser/manual/responsive checks complete; a live camera test on a supported device remains open. Keep Phase 6 in progress until that device-specific check can be performed or manual fallback is explicitly accepted as sufficient.

### Phase 7 — Mentorship, opportunities, and meetup matching (verified for current scope)

**Objective:** Help students connect with verified alumni mentors, give authorized members a place to share opportunities, and suggest upcoming events based on preferences members explicitly choose.

**Work implemented so far:**

- Added mentor profiles that require verified alumni status and allow mentors to list topics and pause new requests.
- Added student-to-mentor requests, duplicate active-request prevention, and mentor-only accept/decline decisions.
- Added explicit mentor match explanations based on shared department and graduating year.
- Added job, internship, scholarship, volunteer, and other opportunity listings; verified alumni, organizers, and administrators can publish, while publishers and administrators can close listings.
- Added saved event location/date preferences and upcoming event suggestions with an explanation of the matching criteria.
- Added a combined signed-in Community page and server-side authorization/CSRF-protected POST routes.
- Added Phase 7 database tables and a repeatable migration. It has been applied twice to the local development database.

**Verification:** PHP syntax checks passed for all 44 PHP files at initial Phase 7 closeout. The repeatable migration ran twice, all four Phase 7 tables were confirmed in MySQL, and the no-preferences event-match query executed successfully. Repository checks covered verified-mentor eligibility, request transitions, opportunity access/close rules, and meetup filter behavior. Disposable browser workflows exercised mentor-profile enablement, student request submission, mentor acceptance, opportunity publishing, and author closing. Signed-in CSRF failure and insufficient-role publish requests both returned HTTP 403. The Community page had no horizontal overflow at tested 375px and 1425px viewports. Anonymous `/community` redirected to login with HTTP 303, and a GET to a POST-only route returned 405. All Phase 7 fixtures were cleaned up.

**Status:** Complete for the Phase 7 feature scope implemented here; broader production security, institution policy, and deployment checks remain part of the later readiness work.

### Phase 8 — Event discussions, memories, and notifications

**Objective:** Give signed-in members a moderated way to discuss events, share event memories, and receive updates about relevant activity.

**Work completed:**

- Added event discussions with soft deletion and authorization for comment authors, event organizers, and administrators.
- Added Memory Lane for started events and JPEG/PNG uploads with a 2 MB limit, dimension/pixel bounds, random storage names, private storage outside `public/`, and authenticated image streaming.
- Kept uploaded images pending moderator approval and restricted pending/rejected image visibility; administrators can approve, reject, or remove photos.
- Added an in-app notification inbox and ownership-checked read-state updates for discussions, mentorship decisions, alumni-review results, and photo moderation.
- Added repeatable Phase 8 and Phase 9 database migrations and updated fresh-schema definitions.

**Verification recorded:** Both migrations were applied twice. Repository/MySQL checks verified discussion ownership/soft deletion, per-event query limits, private photo visibility, and notification persistence/read ownership. Browser workflows verified comment rendering/escaping/removal, photo upload, pending/rejected privacy, inbox rendering/read state, and administrator approval/rejection and alumni decisions. Anonymous/insufficient-role access was rejected. Responsive checks found no horizontal overflow at tested mobile and desktop widths. The photo route returned private/no-store, `nosniff`, and restrictive CSP headers. Temporary fixtures were cleaned; the database returned to five users, zero events/photos/notifications, and the unrelated audit row was preserved. Final syntax checks passed for all 58 PHP and 3 JavaScript files.

**Status:** Implemented for the current feature scope. Broader moderation/report workflows, automated regression tests, and full security/accessibility review remain in the later readiness work.

### Phase 9 foundation — Account audit and operations (partial)

**Objective:** Give project administrators a safe way to confirm that user registration and profile data are being persisted without exposing authentication secrets.

**Work completed on 22 October 2026:**

- Added an administrator-only, read-only `/admin/users` page with account search and pagination.
- The page shows account ID, name, email, assigned roles, active/suspended status, registration time, last sign-in, profile completion, selected professional/education fields, and alumni-verification status.
- The repository query intentionally does not select password hashes. Plaintext passwords are not stored or recoverable by administrators.
- Added the page to the navigation for administrators only and documented its route.
- Granted the local development account `Sourav Sreekanth` the `admin` role while retaining `student`; recorded the change in the local database audit log. This local role assignment is not part of the source commit or GitHub deployment.

**Verification recorded:** All 47 PHP files passed syntax validation. The MySQL account-list and search queries ran, and results did not contain `password_hash`. Browser verification showed an administrator can open the audit page; a signed-in student received HTTP 403; anonymous access redirected to login with HTTP 303. A later responsive check found no horizontal overflow at 375px and 1425px viewport settings, and password hashes were absent from the rendered page. Disposable test accounts were removed, returning the local database to five accounts.

**Additional work completed:** Added an administrator operations page with pending alumni review, photo moderation, basic counts, CSRF-protected decisions/deletion, audit logging, and member notifications. Added CSRF-protected account suspension/reactivation and role grant/removal controls to the account audit. Account management prevents self-suspension/self-role changes, preserves at least one role on each account, and prevents suspending or removing the role from the last active administrator. Granting the alumni role creates a pending alumni profile rather than verifying the account. Added a read-only audit-log page that omits raw metadata.

**Account-control verification:** A disposable admin/member browser workflow exercised role grant/removal and account suspension/reactivation. The audit page showed the changed account state and kept newly assigned alumni status pending. A state-changing POST without CSRF returned HTTP 403 without changing account state. Direct repository checks rejected self-suspension, self-role changes, and removal of an account's final role. The account-audit page had no overflow at tested 360px and 1425px viewports. The read-only audit-log route returned HTTP 200 and omitted raw metadata. All QA records were removed afterward.

**Status:** Partial administration foundation. Content-report moderation and richer analytics remain future work.

## 4. Product status at a glance

| Area | Status | Current result |
|---|---|---|
| Public landing page and visual foundation | Complete | Responsive public product introduction |
| Authentication, roles, and local database | Complete for local prototype | Persisted accounts, protected routes, CSRF/session controls |
| Member profiles and directory | Complete | Editable profiles, privacy controls, search and filters |
| Events and RSVP/waitlist workflows | Complete | Organizer publishing, capacity management, FIFO waitlist |
| Attendance passes and reports | Implemented; camera check pending | Manual check-in, duplicate rejection, reports, responsive checks verified |
| Mentorship, opportunities, and preference-based meetup suggestions | Complete for current phase scope | Browser workflows, CSRF/role denial, and responsive checks recorded |
| Event discussions, memory uploads, and notifications | Implemented for current scope | Moderated private uploads, discussions, and in-app inbox |
| Administrator account audit | Implemented; responsive checks verified | Read-only `/admin/users`; no passwords or password hashes exposed |
| Alumni verification decisions, photo moderation, and basic operational metrics | Implemented for current scope | Admin decisions require CSRF and are audited |
| Account suspension and role management | Implemented for current scope | CSRF-protected and audited; self/last-admin and final-role safeguards |
| Broader administration | Not complete | Content-report moderation and richer analytics remain |
| Production deployment and comprehensive quality review | Not complete | Requires later security, accessibility, performance, and deployment work |

## 5. Current technical product

- **Application:** PHP 8.5.11 using PDO and the built-in development server.
- **Database:** MySQL 8.0.46, local database `alumniconnect_dev`, loopback port `3307`.
- **Frontend:** Server-rendered PHP views, semantic HTML, CSS, and vanilla JavaScript.
- **Local address:** `http://127.0.0.1:8000/`.
- **Database storage:** `%LOCALAPPDATA%\AlumniConnect\mysql-data`; this is local development storage, not cloud storage.
- **Source of setup and implementation details:** [README](../README.md), [architecture](./ARCHITECTURE.md), [database design](./DATABASE.md), [testing notes](./TESTING.md), and [task list](../TASKS.md).

## 6. Remaining work and recommended next steps

1. Run a live Phase 6 camera scan on supported browser/device hardware if available; manual entry, duplicate rejection, report totals, and responsive behavior are already verified.
2. Complete Phase 9 with content-report moderation and richer analytics.
3. Run broader integration, security, accessibility, responsiveness, reliability, and performance checks; fix findings and record evidence.
4. Prepare a private pilot deployment with HTTPS, production server configuration, secret management, backups/recovery, monitoring, and institution-approved privacy/verification practices before inviting real users.

## 7. Overall assessment

AlumniConnect has moved beyond a static concept page: it is a working local prototype with persisted identity, profile discovery, event registration/attendance, community workflows, event discussions, private moderated memories, notifications, account suspension/role controls, audit-log review, and basic administration. Its strongest foundations include role-aware access boundaries, privacy controls, transactional event handling, and the separation of RSVP data from actual attendance records.

The project is **partially complete as a product**: Phases 0–5 are complete; Phase 6 core workflows are verified but a supported-device camera test remains open; Phase 7 is verified for its implemented scope; Phase 8 is implemented for its current scope; Phase 9 is partially implemented; and comprehensive quality/deployment readiness remain outstanding. It is suitable for continued local development and demonstration, but not public production use.
