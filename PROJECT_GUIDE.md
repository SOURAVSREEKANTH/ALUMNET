# AlumniConnect Project Guide

## What we are building

AlumniConnect is a college community portal for alumni, students, event organizers, and administrators. Its central workflows are finding people, joining events, recording real attendance, requesting mentorship, and sharing opportunities.

The public experience can use editorial storytelling and restrained cinematic motion. Signed-in workflows should remain quick, accessible, and task-focused. Public animation must not be a prerequisite for understanding or using the application.

## How a request works

Implemented routes use this request flow:

1. The browser requests a URL from the PHP front controller.
2. Routing selects a controller action and HTTP method.
3. Middleware checks the session, role, and CSRF token where applicable.
4. The controller validates and normalizes input (authentication, profile, event creation, and RSVP routes are implemented).
5. Services and repositories enforce authentication and event transition rules; registration and capacity-sensitive event changes write related records transactionally.
6. Repositories use PDO prepared statements to read or write MySQL data.
7. A view renders escaped HTML. JSON APIs are not implemented yet.

Templates do not make authorization decisions. A hidden button is not a security boundary; the endpoint must independently check permissions.

## Important concepts

- **Identity vs. role:** A user account is separate from its assigned roles. A person may hold more than one role; changing roles is never an ordinary profile edit.
- **Alumni verification:** Email verification shows control of an address, not proof of graduation. Graduation verification requires an administrator approval process.
- **RSVP vs. attendance:** RSVP records a person's intent. A check-in records physical attendance. They are different records and must not be conflated in counts or reports.
- **Capacity and waitlists:** A capacity-limited RSVP is serialized transactionally so concurrent requests cannot overbook. The waitlist is persisted in FIFO order, and releasing an attendee place promotes the next active member.
- **QR passes:** A pass is unique to an event and confirmed attendee. Only a hash of the secret is stored; it expires at event end and is not an attendance record until an authorized organizer check-in succeeds.
- **Privacy:** Directory visibility, contact sharing, and event-attendance visibility are separate decisions. Queries must apply the relevant privacy rules before returning identities.
- **Rule-based suggestions:** Recommendations use disclosed matching criteria, such as shared department, skills, or stated preferences. They are not described as AI.

## Learning path

1. Understand the request flow and visual system.
2. Set up configuration, the initial database schema, and PDO access.
3. Implement sessions, authentication, CSRF checks, login throttling, and initial role-aware pages.
4. Add editable profiles, directory search, privacy controls, and transparent suggestions.
5. Publish events and implement transactional RSVPs and waitlists (implemented).
6. Add passes, check-in, and attendance reports.
7. Add mentorship, opportunities, and matching.
8. Add discussion, memories, and notifications.
9. Add organizer and administrator tools.
10. Run integration, security, accessibility, and browser checks.

See [TASKS.md](./TASKS.md) for phase status and [docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md) for the planned module boundaries.

## Current limitations

The responsive public page, authentication, profiles, directory, and Phase 5–6 event publishing, RSVP, QR/manual check-in, and attendance report foundations are implemented. Phase 7 mentorship, opportunity listings, and preference-based event suggestions are in progress. Event publishing is restricted to organizer/admin roles; event tools are restricted to the event's organizer or an administrator. Current routes include `/`, `/login`, `/register`, `/logout`, `/dashboard`, `/profile`, `/directory`, `/events`, `/events/pass`, `/organizer/check-in`, `/organizer/report`, and `/community`. Check-in correction, profile detail pages, full role administration, and production deployment remain planned. Institution-specific department and graduation-year values must be provisioned in the database. The local runtime uses PHP's development server and a loopback-only MySQL instance; XAMPP/Apache is not required for development.
