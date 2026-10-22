# Database Design

## Status

The identity/authentication, member-profile, event/RSVP, attendance, and Phase 7 mentorship/opportunity/meetup-preference schemas are implemented in `database/schema.sql` and maintained in repeatable migrations. The Phase 4 `member_profiles` table stores profile information for all account types; `alumni_profiles` retains alumni verification state. Repeatable Phase 4–7 migration scripts create their tables without replacing existing member data. Department and graduation-batch records are institution-specific and must be provisioned with real values. Discussion, photo, notification, and moderation-report tables remain design proposals.

Use InnoDB, `utf8mb4`, foreign keys, explicit indexes for common filters, and UTC timestamps. The initial SQL uses these conventions. Use integer primary keys unless a feature has a justified public-token requirement. Prefer soft-state fields for moderation and lifecycle history over destructive deletion of records needed for audit.

## Tables and relationships

### Implemented identity and member discovery

- **`users`** — account identity: unique normalized email, password hash, account status, timestamps, and last-login metadata. Never store a plaintext password.
- **`roles`** — controlled role catalog: alumni, student, organizer, administrator.
- **`user_roles`** — many-to-many user/role assignment; unique `(user_id, role_id)`. Role changes require authorization and an audit record.
- **`departments`** — institution department catalog.
- **`graduation_batches`** — a batch within a department, with graduation year and unique `(department_id, graduation_year)`.
- **`alumni_profiles`** — alumni verification state and reviewer metadata. Earlier versions also stored profile fields; those values are copied into `member_profiles` during migration and the legacy columns are retained for compatibility. Email verification is not alumni verification.
- **`member_profiles`** — canonical editable profile details, department and graduating-batch references, visibility, and opt-in email sharing for all members. Private profiles are excluded from the directory.
- **`auth_attempts`** — short-lived HMACed identifier/IP hashes for login and registration throttling; raw email addresses and IPs are not stored in this table.
- **`skills`**, **`user_skills`** — normalized skill catalog and many-to-many profile skills.
- **`interests`**, **`user_interests`** — normalized interests and many-to-many profile interests.

### Events and attendance

- **`events`** — publisher, title/details, UTC start/end, venue, optional capacity (`NULL` means unlimited), and published/cancelled status.
- **`event_rsvps`** — one current RSVP per `(event_id, user_id)` with `attending`, `interested`, `waitlisted`, or `declined` state and timestamps. A unique constraint prevents duplicate RSVP rows.
- **`event_waitlist`** — waitlist history with `waiting`, `promoted`, or `cancelled` status. Queue order is FIFO by creation time and row ID; unique `(event_id, user_id)` preserves one current queue history per member/event. Rejoining a cancelled queue entry places it at the end.
- **`event_passes`** — event/user-specific SHA-256 pass-token hash, issue/expiry/revocation timestamps, and unique event/user pairing. Raw random pass tokens are shown to the attendee once in a private QR/manual-code page and are never stored.
- **`event_checkins`** — actual attendance record with unique `(event_id, user_id)`, check-in timestamp, method (QR/manual), pass reference, and operator. Check-in does not imply or alter RSVP intent.
- **`event_preferences`** — member-selected venue keyword and availability date window used to filter upcoming meetup suggestions. Results state which explicit preference matched; empty preferences leave the event list unfiltered.
- **`mentorship_profiles`** — verified alumni opt-in, topics, and availability state.
- **`mentorship_requests`** — one current request per student/mentor pair with goals and pending/accepted/declined/withdrawn state. Re-request after a declined or withdrawn request reuses the pair row; pending and accepted requests cannot be duplicated.
- **`opportunities`** — authorized author, type, description, optional location/expiry, and open/closed state. Only open, unexpired records are shown in the community board.

### Community and opportunities

- **`event_discussions`** — event, author, message, creation/edit/deletion timestamps. Keep deletion/moderation state instead of silently rewriting history where retention rules require it.
- **`photo_albums`** — event or batch association, owner, title, and moderation state.
- **`event_photos`** — album, uploader, generated storage key, safe metadata/caption, content type/size, moderation state, and timestamps. The original filename is metadata only, never the storage path.

### Operations and accountability

- **`notifications`** — recipient, optional actor, type, related resource reference, read timestamp, creation timestamp.
- **`reports`** — reporter, target resource reference, reason, moderation status, reviewer, and resolution timestamps. Target references need either typed nullable foreign keys with an exactly-one-target constraint or a documented polymorphic strategy with application-level validation; do not assume a free-form ID is a foreign key.
- **`audit_logs`** — implemented initial actor/action/target/safe-metadata structure; publishing an event records `event.published` in the same transaction. Never record passwords, session IDs, QR secrets, or full authentication tokens.

Do not add a user-connections table unless a real connection workflow is implemented.

## Key integrity and transaction rules

1. **RSVP uniqueness:** unique `(event_id, user_id)`; update the existing state instead of inserting duplicate intents.
2. **Capacity:** capacity-sensitive RSVP transitions run in a transaction while holding a row lock on the event; count only `attending` RSVPs. A `NULL` capacity is unlimited; `interested` does not reserve a place.
3. **Waitlist promotion:** when an attendee cancels or changes to interested, promote the earliest active-account waitlisted member and update the RSVP and queue states in the same transaction. Notification delivery is not implemented yet.
4. **Check-in:** the event row is locked in a transaction, and unique `(event_id, user_id)` protects against duplicate QR scans or manual/QR races. Validate active-account, attending RSVP, non-revoked pass, and event-time eligibility server-side. Each successful attendance write is audit logged.
5. **RSVP vs. check-in:** analytics derive separately from `event_rsvps` and `event_checkins`; never mark an attendee as present merely because they RSVP'd.
6. **Ownership:** derive organizer and uploader permissions from persisted records; never accept ownership from client-supplied IDs.
7. **Audit:** event publication and check-in actions are appended to `audit_logs` in the same transaction as the change.
8. **Privacy:** enforce profile, contact, and attendee visibility in query/service logic. Do not expose hidden attendees in counts that identify individuals.
9. **Deletion/retention:** prefer account deactivation and moderation states when referenced records or audit obligations exist. Define retention and anonymization policy before deployment.

## Index plan

At minimum, index:

- `users(email)` unique; `user_roles(user_id, role_id)` unique and reverse role lookup.
- `member_profiles(department_id, batch_id, profile_visibility)` for visible member discovery.
- `alumni_profiles(department_id, batch_id, visibility, verification_status)`.
- Skills/interests join tables in both traversal directions.
- `events(status, starts_at)` and `events(organizer_id, starts_at)`.
- `event_rsvps(event_id, state)` and unique `(event_id, user_id)`.
- `event_waitlist(event_id, status, created_at, id)` and unique `(event_id, user_id)`.
- Unique `(event_id, user_id)` on passes and check-ins.
- `mentorship_requests(mentor_user_id, status, created_at)` and requester/status.
- `opportunities(status, expires_at, type)` and relevant skill joins if those are implemented.
- `event_discussions(event_id, created_at)`.
- `notifications(user_id, read_at, created_at)`.
- `reports(status, created_at)` and `audit_logs(target_type, target_id, created_at)`.

Refine indexes from actual query patterns and `EXPLAIN`; avoid indexing every column.

## Metrics definitions

- **RSVP-to-check-in conversion:** distinct checked-in users divided by attending RSVP users for the event/time period, with empty denominators reported as not applicable.
- **Repeat attendance:** users with check-ins at more than one eligible event in the selected period.
- **Batch participation:** distinct checked-in attendees grouped by their permitted batch/department data.
- **Mentorship engagement:** counts by request state and completion, not a popularity ranking.
- **Event performance:** RSVP states, waitlist count, check-ins, and conversion, with timestamps/filters documented.

Metrics must be computed from persisted data, respect privacy, and identify any seed/demo dataset as demo data.
