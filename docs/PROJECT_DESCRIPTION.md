# AlumniConnect — Project Description

## 1. Project overview

AlumniConnect is a web-based alumni community portal for a college. It is intended to help students and graduates stay connected with their institution and with each other, discover professional and mentoring opportunities, and participate in alumni events.

The project is being developed as a PHP and MySQL college web-programming project. Its public-facing experience introduces the community, while its authenticated area provides the foundation for member accounts and role-aware features. Development is being completed in phases so that the database, access rules, and core workflows can be checked before adding larger features.

**Current implementation status:** The responsive public landing page, authentication/database foundation, profile editor, privacy-aware member directory, Phase 5 event publishing/RSVP/waitlist workflows, and Phase 6 event passes/check-in/attendance reports are implemented. Phase 7 is in progress, adding verified-alumni mentorship, opportunity listings, and preference-based meetup suggestions.

## 2. Problem statement

Alumni communities often rely on disconnected messaging groups, spreadsheets, and one-off event pages. These make it difficult to keep member information organized, distinguish interest in an event from actual attendance, find relevant mentors, or provide a clear process for verifying alumni status.

AlumniConnect is designed as one place where a college community could:

- Maintain member accounts and profiles.
- Discover alumni and students according to privacy preferences.
- Publish events and manage registrations.
- Record event attendance separately from RSVPs.
- Support mentorship and professional opportunities.
- Share community memories and event discussions.
- Give authorized organizers and administrators tools to manage the service.

These are product goals. The landing page, account/database foundation, profile/discovery workflows, event RSVP, and attendance workflows are implemented; the remaining workflows are planned.

## 3. Goals

The project aims to:

1. Provide a clear, responsive introduction to the alumni network.
2. Establish a maintainable PHP application structure without unnecessary frameworks.
3. Store account and role information in a relational database.
4. Use server-side validation and authorization rather than trusting browser controls.
5. Protect sign-in and form workflows with password hashing, sessions, CSRF checks, and request throttling.
6. Design future community features around privacy, accurate attendance records, and auditable administrative actions.
7. Build and verify the application in small phases.

## 4. Intended users and roles

- **Alumni:** Join the network, maintain a profile, find community members, and participate in events. New alumni accounts remain unverified until an administrator reviews them.
- **Students:** Join the network, connect with alumni, and participate in events; future mentoring workflows are planned.
- **Event organizers:** Publish member events, check in confirmed attendees, and view aggregate attendance reports.
- **Administrators:** Publish events and, in future phases, manage roles, review alumni verification, and handle moderation and reporting.

The database contains these four role types. Public registration currently permits only the alumni and student roles; organizer and administrator roles are reserved for provisioning rather than self-selection. Event publishing is available to organizer and administrator accounts; full organizer and administrator interfaces have not yet been implemented.

## 5. Implemented functionality

### Public landing page

- Responsive, editorial-style page introducing the AlumniConnect concept.
- Navigation to the public sign-in and registration pages.
- Local CSS and vanilla JavaScript; no frontend framework or animation package is required.
- Mobile navigation supports keyboard interaction, and motion responds to reduced-motion preferences.
- Local illustrative campus/community imagery is used. It does not represent a specific college or real AlumniConnect event.

### Accounts and authentication

- Registration with name, email, password, password confirmation, and an allowed account type.
- Passwords are stored as password hashes, not as readable passwords.
- Login checks the submitted credentials against persisted account data.
- Logout is a state-changing POST action protected by a CSRF token.
- Successful authentication rotates the session identifier.
- Session cookies are HTTP-only, use `SameSite=Lax`, and have a 30-minute idle timeout. Secure-cookie mode must be enabled when deploying behind HTTPS.
- Failed sign-in and registration requests are rate-limited. The database stores keyed hashes of identifiers and IP addresses for this purpose rather than raw values in the attempt table.
- Authenticated users can access a basic role-aware dashboard.
- Alumni accounts start with verification status `pending`; control of an email address is not treated as proof of graduation.

### Profiles and directory

- All account types can edit a member profile with a display name, department/graduation year, headline, job title, company, biography, visibility, and optional email sharing.
- The authenticated directory supports text search, department and year filters, and pagination. Private profiles are excluded; email appears only when the member opts in.
- Directory suggestions are ordered by shared department and graduating year, with the matching reason displayed rather than an opaque score.
- Pending/rejected alumni verification is shown separately from the alumni role; a visible profile is not presented as proof of graduation.

### Events and RSVPs

- Organizer and administrator roles can publish events immediately with a title, description, venue, UTC start/end times, and optional attendee capacity.
- Signed-in members can RSVP to attend, record interest without reserving a seat, or cancel their response.
- Capacity-limited events place additional attendees on a FIFO waitlist. Cancelling an attending RSVP promotes the earliest active waitlisted member inside the same transaction.
- Event listings show capacity totals and each member's RSVP/waitlist status without exposing other attendees' names.
- Event-specific passes are available to confirmed attendees. Only a SHA-256 hash of each random bearer token is stored; reissuing invalidates the previous token.
- The event organizer or an administrator can check in active attendees with a QR scan or manual code during the event. Check-ins are one-use, transactional, and audited without storing pass codes in audit metadata.
- Organizer/admin reports show RSVP and waitlist totals, check-in totals, and the RSVP-to-check-in percentage without exposing attendee identities.
- Event publication and check-in are recorded in `audit_logs`; notifications, event edits, and event cancellation controls remain future work.
- QR generation uses a locally bundled library. Camera scanning is optional and uses the browser's `BarcodeDetector` where available; manual entry remains available as a fallback.

### Phase 7 community workflows (in progress)

- Verified alumni can create an opt-in mentor profile, list guidance topics, and pause new requests. Students can browse available mentors and send one request per mentor; verified alumni can accept or decline incoming requests.
- Mentor suggestions are ordered by shared graduating year and department, then shared department, and each result explains its match. No hidden demographic or inferred personal traits are used.
- Verified alumni, event organizers, and administrators can publish job, internship, scholarship, volunteer, or other opportunity listings. Active signed-in members can browse current listings; the publisher or an administrator can close a listing.
- Members can save a venue keyword and an availability date window. Upcoming events matching those explicit preferences are listed with the criteria that matched. With no preferences set, all upcoming events are shown.

### Local development setup

- A PHP development server serves files from the `public` document root.
- `run-mysql-local.ps1` starts the existing isolated MySQL instance on loopback for local use.
- A setup script can create a fresh database, schema, limited-privilege application account, and local configuration.
- A seeder creates demo accounts with randomly generated passwords. It does not include fixed demo passwords in source code.

## 6. Planned functionality

The following items remain on the product roadmap:

- Skill/interest tagging and additional profile facets.
- Public, anonymous profile pages; the current directory requires sign-in.
- Opportunity review/moderation and richer mentorship lifecycle features.
- Event discussions, photo/memory sharing, and notifications.
- Administrative review for alumni verification, role changes, reports, and moderation.
- Broader integration, accessibility, security, and performance testing.

See [TASKS.md](../TASKS.md) for phase-by-phase progress.

## 7. Technology and architecture

### Technology stack

- **Server-side language:** PHP 8 or newer.
- **Database:** MySQL 8 for the current local setup; the schema uses standard MySQL/InnoDB features.
- **Database access:** PDO with prepared statements.
- **Frontend:** semantic HTML, CSS, and vanilla JavaScript.
- **Local web server:** PHP's built-in development server, bound to `127.0.0.1`.
- **Configuration:** safe example configuration plus a private local configuration file, or environment overrides.

No Composer or npm install is required. The QR encoder used by the event pass is bundled locally with its MIT license.

### Request and code organization

The current request path is:

`Browser → public router/front controller → controller → authentication service/repository → PDO/MySQL → escaped PHP view`

- `public/` is the web document root; application internals and configuration are outside it.
- `public/index.php` contains the current explicit route table.
- `public/router.php` supports routing through PHP's built-in development server.
- `app/Controllers/` handles HTTP input and response decisions.
- `app/Services/` contains authentication workflow logic.
- `app/Repositories/` contains prepared database access for accounts, member profiles, and events/attendance.
- `app/Security/` contains session, authentication, CSRF, and rate-limiting helpers.
- `app/Views/` contains the rendered pages and shared layout.
- `config/` contains the safe configuration example and ignored local configuration.
- `database/` contains the SQL schema, local setup/demo seeding scripts, and the Phase 4/5/6/7 migrations.
- `run-local.ps1` and `run-mysql-local.ps1` start the PHP and isolated MySQL development servers in separate terminals.
- `docs/` contains architecture, database, testing, and project documentation.

### Current routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/` | Public landing page |
| GET | `/register` | Registration form |
| POST | `/register` | Validate and create an allowed account |
| GET | `/login` | Sign-in form |
| POST | `/login` | Authenticate an account |
| POST | `/logout` | End the session with CSRF protection |
| GET | `/dashboard` | Authenticated member dashboard |
| GET | `/profile` | View and edit the signed-in member's profile |
| POST | `/profile` | Validate and save the signed-in member's profile |
| GET | `/directory` | Search/filter visible profiles; requires sign-in |
| GET | `/events` | Browse upcoming published events; requires sign-in |
| POST | `/events/create` | Publish an event; organizer/admin and CSRF checks required |
| POST | `/events/rsvp` | Attend, express interest, join waitlist, or cancel; requires sign-in and CSRF |
| POST | `/events/pass` | Issue or refresh a private pass for a confirmed attendee; requires sign-in and CSRF |
| GET | `/organizer/check-in?event={id}` | Open an event check-in desk; event organizer/admin only |
| POST | `/organizer/check-in` | Check in an attendee by QR/manual pass code; organizer/admin and CSRF checks required |
| GET | `/organizer/report?event={id}` | View aggregate RSVP and attendance counts; event organizer/admin only |
| GET | `/community` | Browse mentorship, opportunities, and preference-based event suggestions; requires sign-in |
| POST | `/community/mentor-profile` | Create/update a verified-alumni mentor profile; requires sign-in and CSRF |
| POST | `/mentorship/request` | Request guidance from an available verified alumni mentor; student and CSRF checks required |
| POST | `/mentorship/decision` | Accept or decline an incoming mentorship request; assigned mentor and CSRF checks required |
| POST | `/opportunities/create` | Publish an opportunity; verified alumni/organizer/admin and CSRF checks required |
| POST | `/opportunities/close` | Close a listing; publisher/admin and CSRF checks required |
| POST | `/community/preferences` | Save venue/date preferences for event suggestions; requires sign-in and CSRF |

Notifications, opportunity moderation, and broader administrative routes are not implemented yet.

## 8. Database and data storage

The current local database is named **`alumniconnect_dev`**. Its MySQL data directory is:

`%LOCALAPPDATA%\AlumniConnect\mysql-data`

On the development computer this resolves to:

`C:\Users\Sachin\AppData\Local\AlumniConnect\mysql-data`

The database's files are managed by MySQL; `alumniconnect_dev` is a database directory within that data directory, not a single SQL file. The SQL definition used to create the initial schema is separately maintained in `database/schema.sql`.

The PHP application connects to MySQL with PDO using credentials held in the ignored `config/local.php` (or supplied through supported environment settings). The intended application account has database-scoped read/write permissions rather than schema-administration privileges.

The implemented schema contains:

- **`users`:** account name, normalized email, password hash, account status, and timestamps.
- **`roles`:** the controlled role catalog.
- **`user_roles`:** assignments connecting accounts to roles.
- **`departments`** and **`graduation_batches`:** initial reference data structures for future profile discovery.
- **`alumni_profiles`:** alumni verification state; legacy profile fields are retained for migration compatibility.
- **`member_profiles`:** canonical profile information and visibility/contact-sharing preferences for all member roles.
- **`auth_attempts`:** short-lived throttling records using keyed hashes.
- **`audit_logs`:** initial structure for recording consequential actions; event publication is recorded as `event.published`.
- **`events`, `event_rsvps`, and `event_waitlist`:** published event details, one RSVP state per member/event, and FIFO waitlist history.

Names, email addresses, roles, and profile state are persisted in MySQL when users register. Passwords are persisted only as hashes. Demo account seeding stores password hashes in the database; generated demo passwords are printed for local setup and are not embedded in the project source.

Profile editing and directory queries use `member_profiles`. The Phase 4 migration copies any legacy profile information into the new table without replacing existing profile rows. Private profiles do not appear in search results, and directory email addresses are returned only for members who explicitly enable contact sharing. The signed-in directory orders potential matches by shared graduation batch and department and displays the reason. Department and graduation-year catalogs must be populated with the institution's real values.

Events use UTC timestamps. `NULL` event capacity means unlimited; capacity counts only attending RSVPs, while interested members do not reserve seats. Event RSVP and waitlist transitions are serialized on the event row to prevent overbooking, and an attendee cancellation promotes the next active member in FIFO order. Attendee identities are not displayed in the event listing.

This database currently belongs to the local development environment. It is not a hosted service, is not shared with other users, and is not backed up or production-hardened. If the MySQL server is stopped, the files remain on disk but the application cannot query them until the server is started. Do not delete or move the data directory while MySQL is using it.

## 9. Security and privacy considerations

The current foundation includes:

- Password hashing and verification using PHP's password APIs.
- Prepared statements for database queries.
- Server-side allowlisting of self-registration roles.
- Session ID regeneration after authentication.
- HTTP-only and SameSite session cookies, plus an idle timeout.
- CSRF checks on state-changing authentication and profile forms.
- Rate limiting for login and registration attempts.
- Escaped HTML output for user-provided values.
- Security response headers and a content security policy.
- Local configuration excluded from source control.
- A limited-privilege application database account.

Important limits:

- Local development uses HTTP and disables the secure-cookie flag; use HTTPS and secure cookies for deployment.
- The built-in PHP server is for development only.
- There is no email delivery or email verification workflow yet.
- Alumni graduation verification and administrative review tools are not implemented.
- The demo database is local and has no production backup, monitoring, or recovery procedure.
- Event publication is organizer/admin-only, and event publishing and RSVP forms have CSRF protection. Event edit/cancel controls and attendee notifications have not been implemented.
- Future check-in, upload, and administrative features must implement their own authorization, privacy enforcement, validation, and audit behavior.

## 10. Running and testing

Start MySQL and PHP in separate PowerShell windows from the project directory:

```powershell
powershell -ExecutionPolicy Bypass -File .\run-mysql-local.ps1
```

```powershell
powershell -ExecutionPolicy Bypass -File .\run-local.ps1
```

Open <http://127.0.0.1:8000/> in a browser. Both PowerShell windows must remain open while using database-backed pages. Setup guidance for a new local database and demo seeding is in the [README](../README.md).

The implemented foundation has been checked with PHP syntax validation, JavaScript syntax validation, local database connection checks, browser authentication checks, CSRF and route checks, session-rotation checks, and verification that private configuration/schema files are not publicly served. The documented results and remaining test work are in [TESTING.md](./TESTING.md).

## 11. Summary

AlumniConnect is currently a working local prototype foundation: it introduces the product through a responsive public page, supports database-backed accounts, provides member profile and directory workflows with privacy controls, and supports event publishing, RSVPs, waitlists, check-in, and aggregate attendance reports. Phase 7 mentorship, opportunity, and preference-based meetup workflows are in progress; broader community and administration features remain future phases. The current MySQL data is stored locally on the development computer, not in the cloud.
