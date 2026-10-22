# AlumniConnect — Project and Implementation Guide

**Report date:** 22 October 2026  
**Purpose:** Explain what the project does, why it exists, how its parts connect, what each source file is responsible for, and how to run the local application.

## 1. Executive summary

AlumniConnect is a PHP and MySQL web application for a college community. It brings student and alumni profiles, discovery, events, attendance, mentorship, opportunities, event memories, and basic administration into one authenticated portal.

The project is a **working local prototype**. The PHP application and MySQL database run on the developer's computer; the local setup is not a hosted service and must not be treated as production-ready. Most core feature flows are implemented. Remaining work includes the supported-device camera scan, broad content-report moderation, comprehensive accessibility/security/performance checks, and pilot deployment preparation.

The code uses a deliberately small architecture:

```text
Browser
  → public/index.php (route + HTTP method)
  → Controller (authentication, validation, response)
  → Service (workflow involving files or business rules, when needed)
  → Repository (prepared SQL and transactions)
  → MySQL
  → View (escaped, server-rendered HTML)
```

The browser does not connect directly to the database. Hidden buttons are not security controls: every protected controller/repository operation checks identity, role, ownership, CSRF, or other rules on the server.

## 2. Why the project is needed

Alumni networks often depend on separate spreadsheets, social groups, event sign-up pages, and private messages. That separation makes it difficult to maintain current profiles, discover relevant people, fairly manage event capacity, distinguish a promised attendance from actual attendance, or decide who can publish and moderate content.

AlumniConnect is intended to provide:

- One account and profile foundation for students and alumni.
- Privacy-aware member discovery and contact sharing.
- Event publication and capacity-aware RSVPs.
- A reliable attendance record separate from RSVP intent.
- Mentorship and opportunity sharing.
- Event discussion and moderated memory sharing.
- Administrative workflows for verification, account access, and moderation.

These features are useful only if institutional policy, privacy expectations, operations, and security are addressed alongside the interface. In particular, an email address is not evidence of graduation, and local demo accounts are not real users.

## 3. Features and who uses them

| Feature | Intended users | What it does | Why it exists / important rule |
|---|---|---|---|
| Public landing page | Visitors | Explains the portal and links to registration/sign-in | Introduces the service without exposing private member data |
| Registration and login | Students, alumni | Creates allowed accounts and authenticates them | Uses password hashes, sessions, CSRF protection, and attempt throttling; self-registration does not grant organizer/admin |
| Member profiles | All signed-in members | Saves professional, education, biography, visibility, and contact-sharing information | A member controls profile visibility; email is shared only by explicit opt-in |
| Directory | Signed-in members | Searches by text, department, or graduation year and shows transparent shared-background suggestions | Private profiles are excluded and query results enforce privacy before rendering |
| Events and RSVPs | Members; organizers/admins publish | Lists events and supports attending, interested, cancellation, limited capacity, and a FIFO waitlist | Database transactions prevent overbooking; “interested” does not reserve a seat |
| Event pass and check-in | Confirmed attendees; event organizer/admin | Gives each confirmed attendee a private QR/manual code and records one-use check-in | RSVP intent is not attendance; only a successful authorized check-in counts as attendance |
| Mentorship | Students and verified alumni | Students request guidance; verified alumni can opt in and accept/decline requests | Only verified alumni can mentor; duplicate active requests are prevented |
| Opportunities | Eligible alumni, organizers/admins, members | Eligible publishers post listings; authors/admins can close them | Keeps listings associated with authorized publishers and lifecycle state |
| Meetup matching | Signed-in members | Filters upcoming events using member-entered venue/date preferences | Matches use explicit preferences and explain their criteria; this is not AI |
| Event discussion | Signed-in members | Adds comments to published events; authorized authors/organizers/admins can remove them | Supports event conversation while preserving server-side ownership checks |
| Memory Lane | Signed-in members | Shows started events, discussions, and approved event photos | Photo uploads are private until moderator approval and stored outside the public web root |
| Notifications | Signed-in members | Shows relevant event, mentorship, alumni-review, and photo-moderation updates | Each inbox/read-state query is scoped to the signed-in owner |
| Administrator account audit | Administrators | Searches account and profile status, without showing password hashes | Lets an authorized manager confirm persistence without revealing authentication secrets |
| Administrator account controls | Administrators | Suspends/reactivates accounts and grants/revokes roles | Changes require CSRF, are audited, prevent self-lockout, preserve one role, and protect the last active admin |
| Administrator operations | Administrators | Reviews alumni verification and photos; displays basic service metrics | Moderation/verification decisions are authorized, audited, and can notify affected members |
| Administrator audit log | Administrators | Displays recent audit actions without raw metadata | Helps review consequential actions while avoiding accidental disclosure of sensitive metadata |

### Current role boundaries

- Public registration allows **student** and **alumni** only.
- Organizer and administrator roles are provisioned by an administrator or local setup, not self-selected at registration.
- Event publishing is organizer/admin-only.
- Event check-in and reports are limited to the event organizer or an administrator.
- Mentorship requires a verified alumni mentor; new alumni accounts begin pending.
- Administrators are subject to self/last-admin safety checks when changing accounts.

## 4. How the components connect

### Example: a member RSVP

1. The browser submits the event form to `POST /events/rsvp`.
2. `public/index.php` selects the route by both path and method.
3. `EventController` requires a signed-in user, validates the event/action, and verifies CSRF.
4. `EventRepository::respond()` locks the event row and updates RSVP/waitlist state in a transaction.
5. If a place becomes available, the next eligible active waitlisted member is promoted in FIFO order.
6. The controller redirects with a user-facing status; the event view renders the persisted result.

### Example: an administrator approves a memory

1. A signed-in member uploads a JPEG/PNG through `POST /events/photo`.
2. `MemoryController` validates identity, CSRF, event ID, and submitted fields.
3. `MemoryUploadService` checks upload success, content signature/dimensions, file size, and storage safety; it assigns a random key and writes outside `public/`.
4. `MemoryRepository` creates photo metadata with `pending` status.
5. `AdminController` and `AdminRepository` require the administrator role and CSRF before approval/rejection.
6. The repository updates moderation state, inserts an audit record, and creates a notification in a transaction.
7. The authenticated photo route checks the current member's permission before streaming bytes.

### Example: safe account role change

1. An administrator submits a CSRF-protected role form from `/admin/users`.
2. `AdminController` validates the user ID, role slug, and grant/revoke action.
3. `AdminRepository` locks administrator/account state and checks invariants.
4. The role change and audit entry commit together; granting the alumni role creates a pending alumni profile if one does not already exist.
5. The account audit page reloads from the database and displays the new role/status.

## 5. File-by-file project map

The tables below cover the application source, database scripts, runtime assets, configuration, and project documentation in the current repository.

### Root files and local configuration

| File | Responsibility | Why it is present / how it connects |
|---|---|---|
| `README.md` | Main entry point with quick start, database setup, migrations, and documentation links | The first place to look when checking out or running the project |
| `PROJECT_GUIDE.md` | Short guide to project concepts and implementation approach | High-level onboarding; this report adds the current detailed file map |
| `TASKS.md` | Phase roadmap and completion state | Prevents planned work from being mistaken for completed work |
| `.gitignore` | Excludes local secrets, runtime/private uploads, and generated files | Keeps personal configuration and uploaded memories out of source control |
| `run-mysql-local.ps1` | Starts the isolated MySQL server on loopback port 3307 | Opens/uses the local database data directory; run in its own PowerShell window |
| `run-local.ps1` | Validates PHP/PDO MySQL and starts the PHP development server on 127.0.0.1:8000 | Serves only `public/`; run in a second PowerShell window |
| `config/config.example.php` | Safe placeholder database/application configuration | Documents config keys without embedding real credentials |
| `config/load.php` | Merges example defaults, ignored local configuration, and environment overrides | Shared configuration entry point used by the application and connection layer |
| `config/local.php` | Generated local database credentials, app key, and cookie setting | Private local file; ignored by Git. Do not publish, paste, or commit it |

### Application bootstrap and database

| File | Responsibility | Connection to other components |
|---|---|---|
| `app/bootstrap.php` | Sets response security headers, installs the `AlumniConnect\` autoloader, and configures safe exception handling | Loaded by the front controller before routing; provides the shared runtime foundation |
| `app/Database/Connection.php` | Creates and reuses a PDO MySQL connection using `config/load.php` | Used by repositories; rejects incomplete config and reports database failures safely |
| `app/Database/DatabaseUnavailable.php` | Typed exception for missing/unavailable application database setup | Lets the bootstrap distinguish setup/connectivity errors from unexpected failures |

### Controllers — HTTP entry points

| File | Responsibility | Main connections |
|---|---|---|
| `app/Controllers/PageController.php` | Renders the public home page and signed-in dashboard | Uses the current user/session and page views |
| `app/Controllers/AuthController.php` | Handles login, registration, and logout requests | Calls `AuthenticationService`, session/CSRF helpers, then renders or redirects |
| `app/Controllers/ProfileController.php` | Loads/saves a profile and validates directory search/filter/page input | Uses `ProfileRepository`; renders profile and directory views |
| `app/Controllers/EventController.php` | Handles event listing/publishing, RSVP, passes, check-in, and reports | Uses `EventRepository`; enforces member/organizer/admin access and form validation |
| `app/Controllers/CommunityController.php` | Handles mentor profiles/requests, opportunities, and event preferences | Uses `CommunityRepository`; validates input and CSRF for mutations |
| `app/Controllers/MemoryController.php` | Handles Memory Lane, discussions, photo uploads/deletion, and protected photo streaming | Uses `MemoryRepository` and `MemoryUploadService`; applies authentication/CSRF/privacy checks |
| `app/Controllers/NotificationController.php` | Loads a member's inbox and processes mark-read action | Uses `NotificationRepository`; limits all results to the authenticated owner |
| `app/Controllers/AdminController.php` | Handles account search/audit, audit-log page, operational dashboard, moderation, alumni decisions, and account controls | Uses `AdminRepository`; requires administrator role and CSRF for state changes |

### Repositories — MySQL persistence and business transitions

| File | Responsibility | Main data/flow |
|---|---|---|
| `app/Repositories/UserRepository.php` | Finds login accounts, creates accounts and initial roles/profiles, records login state, reloads authenticated users/roles | `users`, `user_roles`, `member_profiles`, and `alumni_profiles` |
| `app/Repositories/DuplicateAccountException.php` | Distinguishes duplicate account/email errors from other persistence failures | Used by registration to return a meaningful duplicate-account response |
| `app/Repositories/ProfileRepository.php` | Loads/saves profiles and departments/batches; searches visible members with filters/pagination | Enforces profile/contact privacy in query results |
| `app/Repositories/EventRepository.php` | Loads/publishes events; transitions RSVPs/waitlists; issues/validates passes; records check-in and reports | Uses transactions, row locks, hashes, unique constraints, and audit records |
| `app/Repositories/CommunityRepository.php` | Enforces mentor eligibility, request transitions, opportunity publishing/closing, and explicit preference matching | Persists Phase 7 workflows and transparent match criteria |
| `app/Repositories/MemoryRepository.php` | Queries past-event community content and persists comments/photos with visibility and ownership checks | Discussion and photo metadata; moderation is represented separately from physical file storage |
| `app/Repositories/NotificationRepository.php` | Creates notifications and reads/marks the current user's inbox | Notification creation can join a caller's business transaction |
| `app/Repositories/AdminRepository.php` | Loads metrics/queues, account audit/search, audit entries, and performs audited administrative transitions | Alumni verification, photo moderation, accounts/roles/status, and last-admin protections |

### Services, security, and support

| File | Responsibility | Why it exists / callers |
|---|---|---|
| `app/Services/AuthenticationService.php` | Coordinates login validation/throttling/password verification and registration rules | Keeps account workflow logic out of the HTTP controller |
| `app/Services/LoginResult.php` | Structured outcome from an authentication attempt | Lets controller distinguish success, validation failure, or throttling without guessing |
| `app/Services/MemoryUploadService.php` | Validates and securely stores/deletes uploaded image files | Separates filesystem checks from HTTP handling and photo metadata SQL |
| `app/Security/Auth.php` | Resolves current active user and tests assigned roles | Controllers/layout use it for role-aware access and navigation |
| `app/Security/Session.php` | Starts, rotates, expires, and destroys sessions; handles flash state | Authentication depends on it; cookies are HTTP-only and SameSite |
| `app/Security/Csrf.php` | Generates and validates anti-CSRF tokens | State-changing forms/controllers use it |
| `app/Security/LoginRateLimiter.php` | Throttles login/registration attempts with keyed identifiers | AuthenticationService uses it to reduce brute-force abuse and avoid storing raw attempt identifiers |
| `app/Support/View.php` | Escapes HTML, renders pages/layouts, redirects, and emits standard error pages | Shared by all controllers and templates |

### Views — rendered interface

| File | What it renders | Used by |
|---|---|---|
| `app/Views/layout.php` | Shared HTML shell, navigation, user-aware links, flashes, stylesheet/scripts, footer | Wraps rendered page templates |
| `app/Views/pages/home.php` | Public landing page | `PageController::home()` |
| `app/Views/pages/login.php` | Sign-in form and validation message | `AuthController` |
| `app/Views/pages/register.php` | Account registration form | `AuthController` |
| `app/Views/pages/dashboard.php` | Signed-in welcome/role overview and feature links | `PageController::dashboard()` |
| `app/Views/pages/profile.php` | Member profile editor and privacy choices | `ProfileController` |
| `app/Views/pages/directory.php` | Search/filter results, match reasons, and pagination | `ProfileController` |
| `app/Views/pages/events.php` | Event list, RSVP state, organizer publishing, and event community include | `EventController` |
| `app/Views/pages/event-pass.php` | Private attendee pass/QR and manual code | `EventController`; QR script enabled by shared layout |
| `app/Views/pages/event-check-in.php` | Organizer check-in desk, manual form, optional camera scanner | `EventController`; event-tools script |
| `app/Views/pages/event-report.php` | Aggregate event RSVP/waitlist/check-in metrics | `EventController` |
| `app/Views/pages/community.php` | Mentor listings/requests, opportunities, and preference-based meetups | `CommunityController` |
| `app/Views/pages/memory-lane.php` | Started events, event comments, approved photos, member upload controls | `MemoryController` |
| `app/Views/partials/event-community.php` | Reusable discussion/photo controls embedded in event cards/pages | Included by events view |
| `app/Views/pages/notifications.php` | Current member's inbox and read state | `NotificationController` |
| `app/Views/pages/admin-users.php` | Searchable account audit and account status/role controls | `AdminController::users()` |
| `app/Views/pages/admin-operations.php` | Alumni verification queue, photo moderation, operational metrics | `AdminController::operations()` |
| `app/Views/pages/admin-audit-log.php` | Paginated read-only audit history | `AdminController::auditLog()` |
| `app/Views/pages/error.php` | Standard error/status page | `View::error()` |

Templates escape user-controlled output. They improve usability, but authorization remains in controllers/repositories.

### Public entry point and browser assets

| File | Responsibility | Why it matters |
|---|---|---|
| `public/index.php` | Front controller and explicit GET/POST route table | Rejects unknown methods/routes; protected resources are routed through controllers |
| `public/router.php` | Routes non-file requests through `index.php` when using PHP's built-in server | Makes local URLs such as `/events` work without Apache rewrite setup |
| `public/.htaccess` | Apache rewrite fallback and directory listing protection | Supports a conventional Apache setup; deployment must still configure the correct document root |
| `public/assets/css/site.css` | Site design system, page layouts, form/table states, and responsive behavior | Shared styling; no frontend CSS package is required |
| `public/assets/js/site.js` | Mobile navigation, reveal effects, progress/parallax, reduced-motion handling | Progressive enhancement; core server-rendered pages do not rely on it for authorization |
| `public/assets/js/event-tools.js` | Renders QR on attendee pass and optionally scans with browser camera support | Manual check-in remains fallback when camera APIs are missing or denied |
| `public/assets/js/vendor/qrcode.min.js` | Locally bundled QR encoder | Avoids sending event pass contents to a third party; see adjacent license |
| `public/assets/js/vendor/LICENSE-qrcodejs.txt` | License for bundled QR encoder | Retains required license notice |
| `public/assets/images/campus.jpg` | Illustrative landing-page campus image | Visual asset only; not a claim about a real institution |
| `public/assets/images/reunion.jpg` | Illustrative community/reunion image | Visual asset only; not a real project event |
| `public/assets/images/favicon.svg` | Browser/site icon | Branding asset |

### Database files and scripts

| File | Responsibility | When/how it is used |
|---|---|---|
| `database/schema.sql` | Complete current schema for a fresh installation | Applied by `database/setup-local.php` |
| `database/setup-local.php` | Creates a new database, applies schema, creates a scoped application account, and writes ignored local config | First-time setup only; refuses to overwrite existing database/config resources |
| `database/seed-demo.php` | Creates/updates local demo roles/accounts with randomly generated passwords | Optional local demonstration; password reset prints new credentials |
| `database/migrate-phase4.php` | Applies profile-table migration/backfill | Existing databases that predate profile feature |
| `database/migrate-phase5.php` | Applies event, RSVP, and waitlist tables | Existing databases that predate event feature |
| `database/migrate-phase6.php` | Applies hashed pass/check-in tables | Existing databases that predate attendance |
| `database/migrate-phase7.php` | Applies mentor, request, opportunity, and event-preference tables | Existing databases that predate community features |
| `database/migrate-phase8.php` | Applies discussions, photo albums/photos, and initial notification tables | Existing databases that predate community content |
| `database/migrate-phase9.php` | Extends notification enum values for later moderation/review notices | Databases that already have Phase 8 notification table but lack Phase 9 types |
| `database/migrations/phase4-member-profiles.sql` | Phase 4 schema and backfill SQL | Used by phase 4 runner |
| `database/migrations/phase5-events.sql` | Phase 5 event/RSVP/waitlist SQL | Used by phase 5 runner |
| `database/migrations/phase6-attendance.sql` | Phase 6 pass/check-in SQL | Used by phase 6 runner |
| `database/migrations/phase7-community.sql` | Phase 7 mentorship/opportunity/preference SQL | Used by phase 7 runner |
| `database/migrations/phase8-community-content.sql` | Phase 8 discussion/photo/notification SQL | Used by phase 8 runner |
| `database/migrations/phase9-admin-operations.sql` | Phase 9 notification type update | Used by phase 9 runner |

Migrations are for upgrading an older database. Do not blindly run setup against the existing project database. Run schema migrations as an authorized local MySQL administrator; the normal `alumni_app` account is intentionally not granted schema-administration permissions. Take a verified backup before migrating any database that contains data you need.

### Other project documents

| File | Purpose |
|---|---|
| `docs/PROJECT_GUIDE_REPORT.md` | This comprehensive report: feature guide, architecture connections, file map, setup, and limitations |
| `docs/PROJECT_DESCRIPTION.md` | Product purpose, users, feature details, routes, architecture, and security/privacy notes |
| `docs/PROJECT_PHASES_REPORT.md` | Historical phase-by-phase work and verification record |
| `docs/ARCHITECTURE.md` | Boundaries, route map, data flow, design principles, and roadmap dependencies |
| `docs/DATABASE.md` | Entity relationships, integrity rules, and storage rationale |
| `docs/TESTING.md` | Actual checks performed and planned test coverage |

## 6. How to run the application locally (Windows)

### Requirements

- Windows PowerShell.
- PHP 8+ with PDO MySQL. This workspace has a user-local PHP 8.5.11 runtime.
- The isolated local MySQL server and data directory used by this workspace.
- A browser.

No Composer install, npm install, frontend framework, or external QR service is required.

### Start the project already configured on this computer

Open PowerShell with the project directory as the current folder. It is named `phpalumniproject'` (the final apostrophe is part of the current folder name).

**PowerShell window 1 — database:**

```powershell
powershell -ExecutionPolicy Bypass -File .\run-mysql-local.ps1
```

Keep this window open. MySQL listens only on `127.0.0.1:3307`.

**PowerShell window 2 — PHP application:**

```powershell
powershell -ExecutionPolicy Bypass -File .\run-local.ps1
```

Keep this window open as well. Then open:

```text
http://127.0.0.1:8000/
```

Press **Ctrl+C** in each window when finished. Start MySQL before PHP if both need starting. The PHP server uses the `public` directory as its web root; application code, configuration, and storage should not be exposed as public files.

### First-time setup on a different local database

The current workspace already has `config/local.php` and a local database. **Do not run the fresh setup script on this existing database.** For a separate fresh database, enable PDO MySQL, set a database administrator connection, and run `database/setup-local.php` once. See the commands in [README.md](../README.md#run-locally) and [its setup section](../README.md#set-up-another-local-database). Setup intentionally refuses to replace an existing database or local configuration.

For optional demo accounts:

```powershell
& $php @phpArgs .\database\seed-demo.php
```

The seeder prints randomly generated passwords when it creates accounts. Save them securely if needed; they are not source-code defaults. `--reset-passwords` intentionally rotates and prints demo passwords. Do not use demo accounts or credentials for a public service.

### Existing database upgrades

Only if a separate existing database is older than the relevant feature, apply missing migration scripts in phase order (Phase 4 through Phase 9). The README includes the exact PowerShell commands. Apply migrations as a database administrator, not with the normal application account. The current local DB has already had its migrations applied; running upgrade scripts is unnecessary for simply starting the app.

## 7. Important design and security decisions

- **Passwords:** Stored with PHP password hashing; administrators cannot view or recover plaintext passwords.
- **Sessions:** Session ID rotates after successful authentication. Cookies are HTTP-only and SameSite; secure-cookie mode must be enabled behind HTTPS.
- **CSRF:** State-changing POST forms require a session token. GET requests are not used for state changes.
- **SQL:** Repositories use PDO prepared statements for data values. Capacity-sensitive transitions use database transactions/locks.
- **Access control:** Roles and resource ownership are checked server-side for each protected action.
- **Profile privacy:** Private profiles are excluded from directory queries. Email sharing is opt-in.
- **Alumni verification:** Alumni registration or role assignment creates pending verification. An institution must decide which evidence is sufficient.
- **RSVP versus attendance:** RSVP shows intent. An attendance row is created only after valid organizer check-in.
- **Event passes:** High-entropy pass secrets are event-specific; a hash is stored, not the pass code. Reissue invalidates the prior pass.
- **Memory uploads:** Only JPEG/PNG images within file/dimension/pixel bounds are accepted. Random storage keys are used; files live under `storage\memories`, outside `public`, and are streamed through an authenticated authorization check.
- **Moderation and audit:** Alumni/photo decisions and account status/role changes are recorded in `audit_logs`; audit display intentionally omits raw metadata.
- **Secrets and uploads:** `config/local.php` and uploaded memories must never be committed. Real pilot backups need a documented privacy/retention policy.
- **Browser scripts:** Camera scanning uses browser APIs where available and can be denied/unsupported; manual pass entry remains available.

These are useful prototype safeguards, not a substitute for a security review or operational controls for real users.

## 8. Current state, verification, and remaining work

### Implemented and exercised

- Authentication foundation, role checks, sessions, CSRF, and request throttling.
- Member profile editing, privacy-aware directory search, filters, and match explanations.
- Event publishing, RSVP capacity/waitlist transitions, pass issuance, manual check-in, and aggregate reports.
- Mentorship, opportunities, and preference-based meetup suggestions.
- Event discussions, private image upload/moderation, Memory Lane, and in-app inbox.
- Admin account review, suspension/reactivation, role controls, alumni decisions, photo moderation, operational metrics, and read-only audit log.
- Repeated database migration checks, browser workflow checks, access-denial/CSRF checks, PHP/JavaScript syntax checks, responsive viewport checks, and private photo response-header checks.

### Open limitations

- Live camera scanning has not been validated on a supported physical browser/device; manual check-in works as a fallback.
- Broad member content-report/appeal moderation is not implemented.
- Formal automated unit/integration test suites and a complete accessibility/security/performance audit remain future work.
- Institution-specific department/year catalogs and acceptable alumni verification evidence must be provided by the institution.
- Event editing/cancellation and richer analytics remain incomplete.
- Current runtime uses PHP's built-in development server and local loopback MySQL; no hosted environment, domain, HTTPS, backups, monitoring, email delivery, or incident/recovery process is configured.

The project is suitable for **local development and demonstration**. It is not ready for a public launch or real institutional/member data.

## 9. Recommended reading order

1. [README.md](../README.md) — start and configure the local app.
2. This report — understand the feature/file map.
3. [docs/PROJECT_DESCRIPTION.md](./PROJECT_DESCRIPTION.md) — product and route details.
4. [docs/ARCHITECTURE.md](./ARCHITECTURE.md) — request flow and design boundaries.
5. [docs/DATABASE.md](./DATABASE.md) — data relationships and invariants.
6. [docs/TESTING.md](./TESTING.md) — evidence and remaining tests.
7. [TASKS.md](../TASKS.md) and [docs/PROJECT_PHASES_REPORT.md](./PROJECT_PHASES_REPORT.md) — roadmap and historical work.

