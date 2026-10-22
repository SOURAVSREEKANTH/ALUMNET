# AlumniConnect Project Guide

AlumniConnect is a PHP/MySQL alumni-community prototype for member profiles and discovery, events and attendance, mentorship, opportunities, event discussions, private memories, notifications, and administrative workflows.

## Current state

The feature work is implemented for the current prototype scope. Live camera scanning on a supported device, broader content-report moderation, comprehensive quality review, and production deployment remain incomplete. This project is suitable for local development and demonstration, not public production use or real institutional data.

## Request flow

```text
Browser → public/index.php → Controller
        → Service (workflow-specific rules/files)
        → Repository (prepared SQL and transactions)
        → MySQL → escaped PHP View → Browser
```

Controllers and repositories enforce authorization on the server. A hidden link or button is not an access-control boundary. State-changing forms use CSRF protection.

## Important concepts

- **Identity and roles:** Accounts are separate from assigned roles. Registration does not grant organizer or administrator access.
- **Alumni verification:** New alumni are pending review. Control of an email address is not proof of graduation.
- **RSVP and attendance:** RSVP records intent; attendance is recorded only after an authorized event check-in.
- **Privacy:** Directory visibility and email sharing are distinct member choices. Private memory uploads stay outside the public document root and require authorized streaming.
- **Suggestions:** Directory and meetup suggestions use explicit, explainable criteria; they are not described as AI.
- **Administration:** Account changes and moderation decisions require server authorization and CSRF validation and are audited.

For full feature descriptions, the current implementation map, file-by-file responsibilities, Windows setup, database notes, testing status, and limitations, see the [detailed project guide report](./docs/PROJECT_GUIDE_REPORT.md). See [TASKS.md](./TASKS.md) for progress, [docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md) for component boundaries, and [README.md](./README.md) to run the application.
