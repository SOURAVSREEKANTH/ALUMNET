# AlumniConnect Task List

## Current phase

**Current focus — Phase 10 quality review and Phase 11 pilot/deployment preparation**

Phase 6 manual check-in, duplicate rejection, report totals, and responsive checks are verified; a live camera scan remains device-specific. Phase 7 workflows, CSRF/role denial, and responsive checks are verified. Phase 8 discussions, private moderated memories, and notifications are implemented for current scope. Phase 9 account audit/log, account suspension/role controls, alumni verification decisions, photo moderation, and basic operational metrics are implemented for the current scope; broader content-report moderation remains.

## Roadmap

- [x] Phase 0 — Inspect the workspace and available local tools.
- [x] Phase 1 — Document the proposed architecture, routes, feature dependencies, visual system, schema, and test strategy.
- [x] Phase 2 — Build the responsive public landing page and design system.
- [x] Phase 3 — Configure PHP/MySQL, authentication, sessions, role assignment/access boundaries, and reproducible demo accounts.
- [x] Phase 4 — Implement alumni profiles, directory search, filters, and transparent suggestions.
- [x] Phase 5 — Implement event publishing, RSVP states, capacity handling, and waitlists.
- [ ] Phase 6 — Implement QR/manual check-in, attendance records, and attendance metrics; manual check-in, duplicate rejection, report totals, and responsive checks now verified; camera scan on supported hardware remains open.
- [x] Phase 7 — Implement verified-alumni mentorship, opportunity listings, and transparent preference-based meetup suggestions; browser workflows, authorization/CSRF, and responsive checks verified.
- [x] Phase 8 — Implement event discussions, private moderated Memory Lane uploads, and notifications; browser/database workflows verified for current scope.
- [x] Phase 9 — Implement organizer/admin workflows, alumni verification, moderation, and analytics for the current prototype scope; account audit/log, suspension/role controls, alumni decisions, photo moderation, and basic operational metrics are implemented. Broader content-report moderation remains future work.
- [ ] Phase 10 — Run integration, security, accessibility, responsiveness, and performance checks.
- [ ] Phase 11 — Finalize the demo workflow and presentation material; Railway Docker deployment configuration and setup documentation are prepared, but no hosted deployment has been created.

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

Phases 2–9 checks are recorded in `docs/TESTING.md`; the cumulative status is in `docs/PROJECT_PHASES_REPORT.md`. Phase 6 still needs a supported-device camera scan. Phase 10 quality review and a hosted demo launch/presentation preparation remain. Railway deployment configuration is prepared in `docs/RAILWAY_DEPLOYMENT.md`; the app has not been deployed and is not production-ready.
