# AlumniConnect Task List

## Current phase

**Phase 7 — Mentorship, opportunities, and meetup matching**  
Status: In progress. Phase 7 schema, migration, repository, controller, views, routes, and responsive styles are implemented. Repository and student-browser workflows have been exercised; mentor decision and opportunity publishing/closing browser workflows, plus authenticated CSRF/role-denial HTTP checks, remain open. Phase 6 is implemented but still awaits manual-code browser submission, supported-device camera scanning, and responsive checks.

## Roadmap

- [x] Phase 0 — Inspect the workspace and available local tools.
- [x] Phase 1 — Document the proposed architecture, routes, feature dependencies, visual system, schema, and test strategy.
- [x] Phase 2 — Build the responsive public landing page and design system.
- [x] Phase 3 — Configure PHP/MySQL, authentication, sessions, role assignment/access boundaries, and reproducible demo accounts.
- [x] Phase 4 — Implement alumni profiles, directory search, filters, and transparent suggestions.
- [x] Phase 5 — Implement event publishing, RSVP states, capacity handling, and waitlists.
- [ ] Phase 6 — Implement QR/manual check-in, attendance records, and attendance metrics; complete live browser/camera and responsive checks.
- [ ] Phase 7 — Implement verified-alumni mentorship, opportunity listings, and transparent preference-based meetup suggestions.
- [ ] Phase 8 — Implement event discussions, Memory Lane uploads, and notifications.
- [ ] Phase 9 — Implement organizer/admin workflows, alumni verification, moderation, and analytics.
- [ ] Phase 10 — Run integration, security, accessibility, responsiveness, and performance checks.
- [ ] Phase 11 — Finalize setup and learning documentation, demo workflow, and presentation material.

## Phase 1 review checklist

- [ ] Confirm or correct the project workspace name (it currently ends in an apostrophe).
- [ ] Review the proposed modular PHP architecture and route boundaries.
- [ ] Review the relational schema outline and privacy/integrity rules.
- [ ] Confirm whether neutral placeholder branding is acceptable until college assets are provided.
- [ ] Confirm the institution's alumni verification process before implementing it.
- [x] Approve Phase 2 before implementation moves beyond design.

## Environment follow-up

- [x] Make a local PHP runtime and PDO MySQL available for development.
- [x] Confirm isolated loopback MySQL configuration and application connectivity.
- [ ] Configure XAMPP/Apache or another production-capable web server only if needed for deployment.
- [ ] Initialize Git when the intended project location is confirmed.

## Verification notes

Phases 2–7 and their executed checks are recorded in `docs/TESTING.md`. Phase 6 browser checks remain open while Phase 7 proceeds. The current app remains a local development foundation, not the full portal.
