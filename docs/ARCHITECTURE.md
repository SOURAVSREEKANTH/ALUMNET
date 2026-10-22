# Architecture and Product Design

## Scope and status

This document records the design and implementation status for AlumniConnect. Phases 0–8 are implemented for their current scope. Phase 9 now includes a read-only account audit, account suspension/role controls, alumni verification decisions, photo moderation, and basic operational metrics. A supported-device Phase 6 camera scan, broader moderation/audit review, quality review, and deployment readiness remain open.

## Principles

- Use PHP 8+, MySQL/MariaDB, semantic HTML, CSS, and vanilla JavaScript.
- Keep persistence relational and use PDO prepared statements.
- Prefer small PHP modules over a framework migration or a large up-front abstraction layer.
- Separate HTTP handling, business rules, data access, rendering, and configuration.
- Require authorization and validation on the server for every protected operation.
- Keep signed-in workflows usable without animation or JavaScript-dependent navigation.
- Make privacy, event attendance, verification, and moderation explicit in the data model.

## Proposed directory structure

```text
public/
  index.php                 Front controller and current route table
  router.php                PHP development-server router
  assets/
    css/
    js/
    images/
app/
  Controllers/
  Middleware/
  Repositories/
  Services/
  Support/                  Validation, escaping, CSRF, response helpers
  Views/
    layouts/
    public/
    auth/
    alumni/
    student/
    organizer/
    admin/
config/
  config.example.php        Safe placeholders only
  local.php                 Generated local secrets; ignored by Git
database/
  migrations/
  seeds/
docs/
tests/
  Unit/
  Integration/
storage/
  uploads/                  Outside the public document root
run-local.ps1               Starts PHP's local development server
```

Use the existing small explicit router in the front controller. Do not add a framework or package solely to follow a fashion trend. Keep secrets in a local configuration file or environment variables; commit only a safe example configuration. `.gitignore` excludes local secrets, uploaded user content, and generated runtime files.

### Request flow

`HTTP request → route/method match → session/auth/CSRF middleware → controller validation → service/business rules → PDO repository → view or JSON response`

Controllers translate HTTP input and output; services enforce workflows and transaction boundaries; repositories contain SQL. Templates escape untrusted output at render time. JSON endpoints use consistent status codes and response shapes. Errors should be logged safely and shown without exposing credentials, tokens, or SQL details.

## Route map

The current route table lists implemented routes and a separate set of routes still planned. Mutations use POST; state-changing actions must not use GET.

| Area | Implemented routes | Access |
|---|---|---|
| Public/authentication | `GET /`, `GET /login`, `POST /login`, `GET /register`, `POST /register`, `POST /logout` | Public; logout requires a valid session and CSRF token |
| Personal | `GET /dashboard` | Authenticated |
| Profiles/discovery | `GET /profile`, `POST /profile`, `GET /directory` | Authenticated; private profiles excluded, email shown only with explicit sharing |
| Events | `GET /events`, `POST /events/create`, `POST /events/rsvp`, `POST /events/discussion`, `POST /events/discussion/delete` | Authenticated; publishing requires organizer/admin, each mutation requires CSRF |
| Memories | `GET /memory-lane`, `POST /events/photo`, `POST /events/photo/delete`, `GET /memory/photo?id={id}` | Authenticated; JPEG/PNG uploads are private pending moderation; image access follows uploader/moderator/approval rules |
| Event passes | `POST /events/pass` | Authenticated confirmed attendee; pass token is private and stored only as a hash |
| Attendance | `GET /organizer/check-in`, `POST /organizer/check-in`, `GET /organizer/report` | Event organizer or administrator; CSRF required for check-in |
| Community | `GET /community`, `POST /community/preferences` | Authenticated members; explicit event date/location preferences |
| Mentorship | `POST /community/mentor-profile`, `POST /mentorship/request`, `POST /mentorship/decision` | Verified alumni opt in; students request; assigned mentors decide |
| Opportunities | `POST /opportunities/create`, `POST /opportunities/close` | Verified alumni/organizer/admin publish; publisher/admin closes |
| Personal | `GET /notifications`, `POST /notifications/read` | Authenticated; owner-only in-app inbox and CSRF-protected read state |
| Administration | `GET /admin/users`, `GET /admin/operations`, `POST /admin/alumni-review`, `POST /admin/photo-review`, `POST /admin/photo-delete`, `POST /admin/account-status`, `POST /admin/account-role` | Administrator only; state changes require CSRF and write audit records |

| Area | Planned routes | Access |
|---|---|---|
| Discovery | `GET /alumni/{id}` | Authenticated; results filtered by privacy and role rules |
| Mentorship | `GET /mentorship`, `POST /mentorship/requests`, `POST /mentorship/requests/{id}/decision` | Authenticated; requester/mentor/admin permissions checked per action |
| Opportunities | `GET /opportunities`, `GET /opportunities/{id}`, `POST /opportunities` | Browse authenticated; posting requires eligible role |
| Organizer | `GET /organizer/events`, `GET /organizer/events/{id}`, `POST /organizer/events`, `POST /organizer/events/{id}`, `POST /organizer/events/{id}/check-in`, `GET /organizer/events/{id}/report` | Organizer for the event or administrator |
| Administration | Audit-log browsing, report/appeal queues, richer analytics | Administrator |

Every protected request must check the authenticated identity and authorization on the server. Resource ownership is checked from persisted data, never trusted from a submitted user ID, role, or organizer ID. Event creation publishes immediately and writes an audit record; RSVP transitions lock the event row, enforce capacity, and promote the earliest active waitlisted member when an attendee releases a seat. Unlimited capacity is represented by `NULL`. Passes contain high-entropy event-scoped secrets; only their SHA-256 hashes are stored. The owning event row is locked for check-in and a unique `(event_id, user_id)` record prevents duplicate scans. Attendee reports show aggregate counts, not attendee identities. Public landing statistics, if later added, must be computed from real records or clearly marked demo data.

## Feature dependencies and implementation sequence

1. **Foundation:** routing, configuration, schema migrations, PDO, sessions, CSRF, auth, role checks, error handling.
2. **Public experience:** shared styles, responsive layout, landing page, accessible motion controls.
3. **Profiles and discovery:** departments/batches, profile visibility, contact preferences, skills/interests, filtered pagination.
4. **Events and RSVP:** event permissions and lifecycle, RSVP uniqueness, transactional capacity enforcement, waitlist promotion.
5. **Check-in and reports:** event-specific pass generation, hashed pass secret, server validation, unique attendance, audit trail, aggregate metrics.
6. **Mentorship and exchange:** mentor eligibility, request states, opportunities and expiry/moderation, transparent preference matching.
7. **Community content (Phase 8):** event discussions, privately stored and moderated photos, and in-app notifications.
8. **Administration (Phase 9):** account audit, suspension/role controls, alumni verification, photo moderation, and basic operational metrics; broader moderation/audit review remain.
9. **Quality review (Phase 10):** integration, security, accessibility, responsive, reliability, and performance checks.
10. **Pilot readiness (Phase 11):** setup/demo documentation and a private deployment plan with HTTPS, secrets, backups, monitoring, and institution-approved privacy/verification policies.

## Visual system

### Direction

Industrial editorial: a dark graphite base, warm off-white typography, steel-gray surfaces, and restrained safety-amber emphasis. Use asymmetry within a clear grid, fine rules, generous negative space, oversized but readable type, and authentic campus/reunion imagery when supplied.

### Token proposal

| Token | Proposed value | Use |
|---|---|---|
| Graphite | `#17191A` | Primary dark background |
| Near-black | `#0D0F10` | Deep sections and image framing |
| Warm white | `#F2F0E9` | Primary light surface and text on dark |
| Steel | `#7D8588` | Secondary text, dividers, metadata |
| Signal amber | `#E5A12B` | Focused emphasis and primary action |

Check contrast for actual text sizes and states before finalizing the palette. Use system font stacks initially; add a licensed local font only if the project later needs it and the asset is available.

### Interaction and motion

- Use IntersectionObserver for simple reveal-on-entry effects.
- Add GSAP/ScrollTrigger only if a specific pinned or coordinated scroll sequence cannot be achieved simply and accessibly; bundle/licensing/setup need review before adoption.
- Respect `prefers-reduced-motion`; provide a static experience with the same content and controls.
- Simplify or disable pinned/parallax scenes on small screens and touch devices.
- Animate primarily transform and opacity; avoid layout-dependent animation loops.
- Ensure keyboard focus, hover, touch, loading, and error states are designed alongside default states.
- Do not use pointer-following behavior as the only way to discover controls.

### Image policy

Prefer college-provided or properly licensed imagery. The current two local editorial images were downloaded from Unsplash (`photo-1562774053-701939374585` and `photo-1523580494863-6f3031224c94`) under the [Unsplash free license](https://unsplash.com/license). They are labelled as illustrative and do not represent the user's college or actual alumni/events. Do not imply that a fabricated alumnus, testimonial, institutional statistic, or event is real.

### Implemented public-page behavior

- Responsive editorial landing page with campus/reunion imagery and semantic anchor navigation.
- Sticky desktop journey panel, horizontally scrollable memory gallery, and mobile-specific layout.
- IntersectionObserver reveals, requestAnimationFrame-throttled scroll progress/parallax, and reduced-motion handling.
- Mobile navigation is keyboard-operable; the navigation remains visible on small screens if JavaScript is unavailable.
- No GSAP, chart, icon, or QR dependency has been introduced; there are no fake statistics or live event claims.
- `run-local.ps1` serves only `public/` through PHP's built-in development server at `127.0.0.1:8000`.

### Implemented authentication foundation

- `database/schema.sql` defines the initial identity, role, alumni-profile, authentication-throttling, and audit tables.
- `database/setup-local.php` provisions a new development database, limited app account, and ignored local config; it refuses to overwrite existing local configuration or database resources.
- Registration accepts alumni/student roles only, stores password hashes, assigns the chosen role, and leaves alumni verification pending.
- Login/logout use regenerated server-side sessions, CSRF protection, an idle timeout, escaped views, and HMAC-keyed login/registration throttling.
- The signed-in interface includes a role-aware dashboard, Phase 4 profile/directory features, Phase 5 event publishing/RSVP workflows, Phase 6 event passes/check-in/reports, Phase 7 community, Phase 8 discussions/memories/notifications, and the current Phase 9 audit/verification/moderation foundation; account role/suspension management and broader administrator workflows remain.

### Implemented profile and directory foundation

- Profiles are editable by their owner; names and profile fields are validated server-side and all rendered user content is escaped.
- Profile visibility is controlled as private, members, or public-listing; only non-private profiles appear in the authenticated directory.
- Email addresses appear in search results only when the profile owner enables contact sharing.
- Alumni with pending or rejected verification are labeled in directory results; profile visibility does not imply graduation verification.
- Search supports member name, headline, company, job title, biography, department, department filter, and graduating-year filter, with paginated results.
- Directory suggestions are ordered transparently by shared department and graduation batch; no hidden attributes or opaque score are used.
- `database/migrate-phase4.php` provisions `member_profiles` using administrator credentials and non-destructively backfills existing rows.
- Department and graduation-batch catalogs must be provisioned with institution-approved values; placeholder college data is not generated.

## Security boundaries

- Use `password_hash()` / `password_verify()`, regenerate session IDs on login, and configure secure cookie flags according to local HTTPS availability and deployment.
- Check authorization in each controller/service entry point, not only in navigation or templates.
- Use CSRF tokens for all state-changing browser requests and apply reasonable login/action throttling.
- Normalize and validate input; escape output for its HTML/attribute/URL/JavaScript context.
- Use prepared statements and transactions for multi-record or capacity-sensitive changes.
- Verify event ownership from the database before organizer operations.
- Validate uploads by size, MIME/content, extension allowlist, and image decoding; generate storage names; prohibit script execution in upload storage.
- Store QR pass secrets as hashes, make tokens event-specific, and never log tokens or session secrets.
- Audit role, verification, moderation, check-in correction, and other consequential administrative changes.

Production hardening still requires HTTPS, backups/restore drills, dependency and PHP patching, mail delivery configuration, secure hosting permissions, monitoring, retention policy, and institution-specific privacy/legal review.

## Open decisions

- Confirm the project workspace path (the currently active directory name ends in an apostrophe).
- Provide college branding and licensed imagery, or approve neutral demo assets for the public page.
- Confirm the institution's alumni proof and approval workflow.
- Decide later whether email delivery is required for verification and notifications; the initial design does not assume a mail provider.
