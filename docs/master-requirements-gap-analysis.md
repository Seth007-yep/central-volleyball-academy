# Master Requirements Gap Analysis

This compares the supplied **Central Volleyball Academy Production Web Application — Master Plan & Development Specification** with the current local project. The attachment is treated as a product and technical requirements reference. Later owner requests take precedence where they narrow or change it.

## Owner decisions already applied

- The official working name is **Central Volleyball Academy**; the design uses yellow and blue.
- GitHub was deferred earlier; the owner has now requested a private GitHub backup. No remote exists yet, and connection to the GitHub account is pending.
- The owner added editable academy/team information, player administration, a photo/video gallery, online registration, and coach biographies, roles, qualifications, and photographs.
- Do not invent academy facts, coach credentials, or media permissions. The current forms and public site use empty/clearly marked content until approved information is supplied.

## Current implementation snapshot

The project has a PHP front controller, PDO repositories, migrations `001`–`006`, admin/staff authentication, an admin dashboard, editable academy text, registrations and review, private player records, gallery uploads/moderation, coach profiles including experience, staff-managed programs and events, and administrator-only staff-account management. Application decisions use a separate confirmation screen. Program schedules and published-coach assignments are supported; upcoming events can use published gallery images and retain visible cancellations. The code supports prepared SQL, CSRF, output escaping, role-aware protected routes, audit events, media validation/private storage, database pagination for most admin lists, and local XAMPP use. See [development progress](development-progress.md), [academy features](academy-features.md), and [security status](security.md).

## Requirements still missing or incomplete

| Area | Status | What remains |
| --- | --- | --- |
| Training programs | Implemented locally; content pending | Admins can create/edit/draft/publish/archive programs, add up to ten schedule notes, assign published coaches, and optionally record age groups, fee details, and capacity. Published programs display on the homepage. The academy must supply the actual approved program, schedule, and fee information; registration is not capacity-linked. |
| Events and activities | Implemented locally; timezone/content pending | Admins can create/edit/draft/publish/cancel/archive upcoming events, set local start/end times, location and registration state, and choose an approved gallery image. Published upcoming events appear publicly; previously published cancellations remain visible. The academy must confirm its timezone and supply approved event data; there is no external registration URL or attendee workflow. |
| Public site structure and copy | Partial | About, Programs, Events, Coaches, Gallery, Registration, and Contact are sections on the homepage. Program and upcoming-event cards now show published records. There is no separate “Why join?” section, and the current hero headline/fallback copy is not academy-specific. Values, facilities, and leadership are not separately displayed, though they could be written in the general About text. Approved content and official assets are still needed. |
| Coach profiles | Implemented locally; content pending | Staff can manage name, role, biography, experience, qualifications, photo, order, and publication state. Actual approved coach details, experience, qualifications, and photos have not been supplied. |
| Registration workflow | Partial | The public form checks for matching name, date of birth, and applicant/guardian contact before creating a row. A keyed advisory lock serializes simultaneous submissions; rejected/cancelled applications can be resubmitted unless a player record exists. New and repeated submissions receive the same generic confirmation. Privacy/registration wording, email notifications, and academy confirmation of the adult-age threshold remain open. |
| Registration search | Mostly implemented | Admins can search name/email and filter by applicant/guardian phone, program-interest text, received-date range, and status, with database filtering and pagination. |
| Member administration | Mostly implemented | Staff can search name/email and filter by player/guardian phone, program-interest text, date-of-birth range, and status, edit player details, and use reversible statuses. Filtering runs in SQL and remains paginated. Export is conditional on academy needs; there is no separate read-only member detail page. |
| Admin dashboard | Implemented for required operational counts | It shows total members, approved members (records created from approved applications), pending registrations, upcoming events, active programs, active staff, published coaches, active players, and recent activity. |
| Staff/user management | Basic roles implemented | Admins can create/update accounts, assign Administrator or Academy Staff, activate/deactivate accounts, and set replacement passwords. Admins cannot remove their own role/access or the last active admin; only admins can manage accounts or view audit logs. Finer per-action permissions and password recovery are not implemented. |
| Action confirmations | Registration decisions confirmed | Every application status decision uses a separate confirmation screen, a session-bound expiring decision token, CSRF validation, and a row-locked check that the application status has not changed. Player status changes still do not have a separate confirmation step. |
| Gallery list scale | Partial | Uploads are validated, stored outside `public/`, and moderated as draft/published/archived. The admin gallery list shows a bounded first page but has no search, filters, or pagination. The academy's actual photo-release/guardian-consent evidence is not stored by the app. |
| Search and filtering | Mostly implemented | Member and registration lists are paginated and use database-side name/email search plus phone, program-interest, date-range, and status filters. Gallery filtering and conditional export remain open. |
| Application logging | Partial | Authentication, registrations, content, coaches, programs, events, player updates, and gallery actions produce audit entries; the web DB account cannot update/delete audit rows. Application exceptions go to PHP's `error_log`, but production log location, retention, monitoring, and alerts are not configured. |
| Privacy and email | Not ready for live use | The registration form is marked for development. A reviewed privacy policy/registration terms, retention schedule, access policy, and confirmed guardian/age rules are missing. No mail-service abstraction or email delivery exists. |
| Performance and SEO | Partial | The site uses organized CSS and lazy-loads gallery/profile images. Image resizing/optimization, responsive image variants, caching policy, and performance measurements are not done. No sitemap, `robots.txt`, canonical URLs, or Open Graph metadata are present. |
| Accessibility and responsive review | Spot checks passed; full audit pending | Semantic landmarks/headings, registration labels, named controls, visible keyboard focus, selected contrast-token pairs, homepage viewport emulation at mobile/tablet/laptop/desktop sizes, and registration at a mobile size were checked. Physical-device coverage, complete keyboard flow, screen-reader review, text zoom, and full WCAG 2.2 AA signoff remain. |
| Security verification | Core smoke coverage expanded; deployment review pending | Local checks cover active/inactive auth outcomes, role predicates, generic failures, rate limiting, logout/session expiry/cookie flags/CSRF token behavior, SQL-shaped search values, shared HTML escaping, upload MIME/size cases, protected-route redirects, and response headers. Signed-in browser denial paths, full multipart upload/streaming, configuration exposure review, and staging-host HTTPS/logging checks remain. |
| Backup and recovery | Documentation only | A provider-neutral plan exists. There is no selected encrypted off-site backup destination, schedule, retention, alerting, or successful restore exercise. The local XAMPP database is not a production backup. |
| Production deployment and launch | Not started | No production host, domain/DNS, HTTPS certificate/redirect, supported production runtime/database, production secrets, or staging environment is configured. The current local PHP 8.0.30 and MariaDB 10.4.32 are not production targets. |
| Documentation set | Partial | Core architecture, setup, schema, security, progress, deployment, backup, feature, and walkthrough docs exist. A staff admin guide, a dedicated troubleshooting guide, and an API guide are not present; the API itself is not required for the initial release. |
| Git workflow | In progress | The owner has requested a private GitHub backup. This checkout has no initial commit or remote; GitHub account connection is required before creating the private repository and pushing. |

## Decisions and content still needed from the academy

- Official logo and exact yellow/blue color values.
- Approved academy history, values, facilities, leadership, contact/social details, programs, schedules, fees (if any), capacity rules, and events.
- Approved coach biographies, roles, experience, qualifications, and photographs.
- Registration eligibility, age/guardian rules, privacy/terms wording, consent, access, and retention policies.
- Whether the academy needs exports, email notices, a contact form, online payments, public player listings, or extra staff roles.
- Media-release and guardian approval procedures, especially for identifiable minors.
- What “basic things every team will ask for” means beyond the items already in the master plan (for example, fixtures/results or standings); these should be confirmed before adding more tables or screens.

The registration form intentionally omits gender, home address, and emergency-contact fields until the academy confirms they are necessary. Analytics and a public API are optional in the master plan and have not been added without a requirement.

The pasted master-plan text contains a malformed, merged paragraph in section 54. This comparison uses the clear requirements around it and does not infer extra requirements from the corrupted fragment.

## Suggested remaining order

1. Confirm academy content and decisions above, especially programs, events, registration rules, and privacy/consent.
2. Supply academy-approved program/event details, confirm the academy timezone, and decide whether event sign-up needs an external link or a registration workflow.
3. Decide email, contact-form, export, and other reporting needs; confirm the duplicate-match rules fit academy operations.
4. Complete the remaining authenticated, physical-device, screen-reader, and end-to-end upload checks in [quality check results](quality-checks.md).
5. Choose production infrastructure, configure HTTPS/secrets/logging/backups, and test restoration before launch.
6. Resume GitHub only when the owner wants it; make local Git commits separately if desired.
